<?php

namespace App\Services\People;

use App\Models\Employee;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class AttendanceReport
{
    public function rows(string $from, string $to, ?int $employeeId = null, ?string $status = null): Collection
    {
        $start = CarbonImmutable::parse($from)->startOfDay();
        $end = CarbonImmutable::parse($to)->endOfDay();
        $employees = Employee::query()->with([
            'jobPosition', 'workPeriods',
            'absenceRequests' => fn ($query) => $query->with('type')->where('status', 'approved')->whereDate('starts_on', '<=', $end)->whereDate('ends_on', '>=', $start),
            'timeEntries' => fn ($query) => $query->with('adjustments')->whereBetween('clocked_in_at', [$start, $end]),
        ])->when($employeeId, fn ($query) => $query->whereKey($employeeId))->orderBy('name')->get();

        $rows = collect();
        foreach ($employees as $employee) {
            for ($date = $start->startOfDay(); $date->lte($end); $date = $date->addDay()) {
                $periods = $employee->workPeriods->where('weekday', $date->dayOfWeek)->values();
                $entries = $employee->timeEntries->filter(fn ($entry) => $entry->clocked_in_at->isSameDay($date))->values();
                if ($periods->isEmpty() && $entries->isEmpty()) {
                    continue;
                }
                $absence = $employee->absenceRequests->first(fn ($request) => $request->starts_on->lte($date) && $request->ends_on->gte($date));
                $row = $this->dailyRow($employee, $date, $periods, $entries, $absence);
                if (! $status || $status === 'all' || in_array($status, $row['incidents'], true) || ($status === 'ok' && $row['incidents'] === [])) {
                    $rows->push($row);
                }
            }
        }

        return $rows->sortByDesc(fn ($row) => $row['date']->format('Y-m-d').'-'.str_pad((string) $row['employee']->id, 10, '0', STR_PAD_LEFT))->values();
    }

    private function dailyRow(Employee $employee, CarbonImmutable $date, Collection $periods, Collection $entries, $absence): array
    {
        $plannedMinutes = $periods->sum(fn ($period) => $this->periodMinutes($period->starts_at, $period->ends_at));
        if ($absence) {
            $plannedMinutes = $absence->is_full_day ? 0 : (int) round($plannedMinutes * max(0, 1 - (float) $absence->requested_days));
        }
        $workedMinutes = $entries->sum(function ($entry): int {
            $out = $entry->clocked_out_at ?? now();

            return max(0, (int) $entry->clocked_in_at->diffInMinutes($out));
        });
        $incidents = [];
        $hasOpen = $entries->contains(fn ($entry) => $entry->clocked_out_at === null);
        $dayFinished = $date->isPast() && ! $date->isToday();

        if ($absence) {
            $incidents[] = 'excused';
        }
        if (! $absence && $periods->isNotEmpty() && $entries->isEmpty() && $dayFinished) {
            $incidents[] = 'missing';
        }
        if ($periods->isEmpty() && $entries->isNotEmpty()) {
            $incidents[] = 'unplanned';
        }
        if ($hasOpen) {
            $incidents[] = 'open';
        }
        if (! $absence && $periods->isNotEmpty() && $entries->isNotEmpty()) {
            $firstPlanned = CarbonImmutable::parse($date->toDateString().' '.$periods->min('starts_at'));
            if ($entries->min('clocked_in_at')->gt($firstPlanned->addMinutes(5))) {
                $incidents[] = 'late';
            }
            if ($dayFinished && ! $hasOpen && $workedMinutes + 5 < $plannedMinutes) {
                $incidents[] = 'incomplete';
            }
        }
        if ($entries->contains(fn ($entry) => $entry->adjustments->isNotEmpty())) {
            $incidents[] = 'corrected';
        }

        return [
            'employee' => $employee, 'date' => $date, 'periods' => $periods, 'entries' => $entries,
            'planned_minutes' => $plannedMinutes, 'worked_minutes' => $workedMinutes,
            'first_in' => $entries->min('clocked_in_at'), 'last_out' => $entries->whereNotNull('clocked_out_at')->max('clocked_out_at'),
            'incidents' => array_values(array_unique($incidents)),
            'absence' => $absence,
        ];
    }

    private function periodMinutes(string $startsAt, string $endsAt): int
    {
        $start = ((int) substr($startsAt, 0, 2) * 60) + (int) substr($startsAt, 3, 2);
        $end = ((int) substr($endsAt, 0, 2) * 60) + (int) substr($endsAt, 3, 2);

        return $end > $start ? $end - $start : ($end + 1440) - $start;
    }
}
