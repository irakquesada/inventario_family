<?php

namespace App\Services\People;

use App\Models\AbsenceRequest;
use App\Models\Employee;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class WeeklyScheduleReport
{
    public function data(string $week): array
    {
        $start = CarbonImmutable::parse($week)->startOfWeek();
        $days = collect(range(0, 6))->map(fn (int $offset) => $start->addDays($offset));
        $employees = Employee::query()->with(['jobPosition', 'workPeriods'])
            ->where('is_active', true)->orderBy('name')->get();
        $absences = AbsenceRequest::query()->with('type')->whereIn('status', ['pending', 'approved'])
            ->whereDate('starts_on', '<=', $days->last())->whereDate('ends_on', '>=', $days->first())
            ->get()->groupBy('employee_id');

        $rows = $employees->map(function (Employee $employee) use ($days, $absences): array {
            $employeeAbsences = $absences->get($employee->id, collect());
            $cells = $days->map(function (CarbonImmutable $date) use ($employee, $employeeAbsences): array {
                $periods = $employee->workPeriods->where('weekday', $date->dayOfWeek)->values();
                $dayAbsences = $employeeAbsences->filter(fn (AbsenceRequest $request) => $request->starts_on->lte($date) && $request->ends_on->gte($date))->values();

                return ['date' => $date, 'periods' => $periods, 'absences' => $dayAbsences];
            });

            return ['employee' => $employee, 'cells' => $cells, 'weekly_minutes' => $this->minutes($employee->workPeriods)];
        });

        return ['start' => $start, 'end' => $start->addDays(6), 'days' => $days, 'rows' => $rows];
    }

    private function minutes(Collection $periods): int
    {
        return $periods->sum(function ($period): int {
            $start = ((int) substr($period->starts_at, 0, 2) * 60) + (int) substr($period->starts_at, 3, 2);
            $end = ((int) substr($period->ends_at, 0, 2) * 60) + (int) substr($period->ends_at, 3, 2);

            return $end > $start ? $end - $start : ($end + 1440) - $start;
        });
    }
}
