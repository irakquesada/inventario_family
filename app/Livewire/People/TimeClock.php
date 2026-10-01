<?php

namespace App\Livewire\People;

use App\Models\Employee;
use App\Services\People\ToggleTimeClock;
use Carbon\CarbonImmutable;
use Livewire\Attributes\Url;
use Livewire\Component;

class TimeClock extends Component
{
    #[Url(as: 'desde')]
    public string $entriesFrom = '';

    #[Url(as: 'hasta')]
    public string $entriesTo = '';

    public function mount(): void
    {
        $this->entriesFrom = $this->normalizedDate($this->entriesFrom, today()->startOfMonth()->toDateString());
        $this->entriesTo = $this->normalizedDate($this->entriesTo, today()->toDateString());
    }

    public function clockIn(ToggleTimeClock $clock): void
    {
        $clock->clockIn($this->employee(), auth()->user(), request()->ip());
        session()->flash('status', 'Entrada registrada a las '.now()->format('H:i:s').'.');
    }

    public function clockOut(ToggleTimeClock $clock): void
    {
        $clock->clockOut($this->employee(), auth()->user());
        session()->flash('status', 'Salida registrada a las '.now()->format('H:i:s').'.');
    }

    public function render()
    {
        $employee = $this->employee();
        $this->entriesFrom = $this->normalizedDate($this->entriesFrom, today()->startOfMonth()->toDateString());
        $this->entriesTo = $this->normalizedDate($this->entriesTo, today()->toDateString());
        $from = CarbonImmutable::parse($this->entriesFrom)->startOfDay();
        $to = CarbonImmutable::parse($this->entriesTo)->endOfDay();
        if ($to->lt($from)) {
            $to = $from->endOfDay();
            $this->entriesTo = $from->toDateString();
        }
        $openEntry = $employee->timeEntries()->whereNull('clocked_out_at')->first();
        $schedule = $employee->workPeriods()->get()->groupBy('weekday');
        $weeklyMinutes = $schedule->flatten()->sum(function ($period): int {
            $start = ((int) substr($period->starts_at, 0, 2) * 60) + (int) substr($period->starts_at, 3, 2);
            $end = ((int) substr($period->ends_at, 0, 2) * 60) + (int) substr($period->ends_at, 3, 2);

            return $end > $start ? $end - $start : ($end + 1440) - $start;
        });

        $entries = $employee->timeEntries()->whereBetween('clocked_in_at', [$from, $to])->limit(500)->get();
        $workedMinutes = $entries->sum(fn ($entry) => $entry->clocked_out_at ? $entry->clocked_in_at->diffInMinutes($entry->clocked_out_at) : 0);

        return view('livewire.people.time-clock', [
            'employee' => $employee, 'openEntry' => $openEntry,
            'entries' => $entries, 'schedule' => $schedule, 'weeklyMinutes' => $weeklyMinutes,
            'workedMinutes' => $workedMinutes, 'entriesFromDate' => $from, 'entriesToDate' => $to,
        ])->layout('layouts.app', ['title' => 'Mi fichaje']);
    }

    private function employee(): Employee
    {
        return Employee::query()->where('user_id', auth()->id())->firstOrFail();
    }

    private function normalizedDate(string $date, string $fallback): string
    {
        try {
            return CarbonImmutable::createFromFormat('Y-m-d', $date ?: $fallback)->toDateString();
        } catch (\Throwable) {
            return $fallback;
        }
    }
}
