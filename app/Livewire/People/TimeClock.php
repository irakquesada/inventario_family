<?php

namespace App\Livewire\People;

use App\Models\Employee;
use App\Services\People\ToggleTimeClock;
use Livewire\Component;

class TimeClock extends Component
{
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
        $openEntry = $employee->timeEntries()->whereNull('clocked_out_at')->first();
        $schedule = $employee->workPeriods()->get()->groupBy('weekday');
        $weeklyMinutes = $schedule->flatten()->sum(function ($period): int {
            $start = ((int) substr($period->starts_at, 0, 2) * 60) + (int) substr($period->starts_at, 3, 2);
            $end = ((int) substr($period->ends_at, 0, 2) * 60) + (int) substr($period->ends_at, 3, 2);

            return $end > $start ? $end - $start : ($end + 1440) - $start;
        });

        return view('livewire.people.time-clock', [
            'employee' => $employee, 'openEntry' => $openEntry,
            'entries' => $employee->timeEntries()->where('clocked_in_at', '>=', now()->subDays(30))->limit(50)->get(),
            'schedule' => $schedule, 'weeklyMinutes' => $weeklyMinutes,
        ])->layout('layouts.app', ['title' => 'Mi fichaje']);
    }

    private function employee(): Employee
    {
        return Employee::query()->where('user_id', auth()->id())->firstOrFail();
    }
}
