<?php

namespace App\Livewire\People;

use App\Models\Employee;
use App\Models\EmployeeWorkPeriod;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class EmployeeSchedule extends Component
{
    public Employee $employee;

    public array $periods = [];

    public function mount(Employee $employee): void
    {
        abort_unless(auth()->user()?->can('people.manage'), 403);
        $this->employee = $employee;
        foreach (range(0, 6) as $day) {
            $this->periods[$day] = $employee->workPeriods()->where('weekday', $day)
                ->orderBy('starts_at')->get()->map(fn (EmployeeWorkPeriod $period) => [
                    'starts_at' => substr($period->starts_at, 0, 5),
                    'ends_at' => substr($period->ends_at, 0, 5),
                ])->values()->all();
        }
    }

    public function addPeriod(int $day): void
    {
        $this->authorizeManagement();
        abort_unless($day >= 0 && $day <= 6, 422);
        $this->periods[$day][] = ['starts_at' => '', 'ends_at' => ''];
    }

    public function removePeriod(int $day, int $index): void
    {
        $this->authorizeManagement();
        abort_unless(isset($this->periods[$day][$index]), 422);
        unset($this->periods[$day][$index]);
        $this->periods[$day] = array_values($this->periods[$day]);
        $this->resetValidation();
    }

    public function save(): void
    {
        $this->authorizeManagement();
        $this->validate([
            'periods' => ['array', 'size:7'],
            'periods.*' => ['array'],
            'periods.*.*.starts_at' => ['required', 'date_format:H:i'],
            'periods.*.*.ends_at' => ['required', 'date_format:H:i'],
        ]);

        if ($this->periodsOverlap()) {
            $this->addError('periods', 'Los turnos se solapan. Revisa también los turnos que terminan después de medianoche.');

            return;
        }

        DB::transaction(function (): void {
            Employee::query()->lockForUpdate()->findOrFail($this->employee->id);
            $this->employee->workPeriods()->delete();
            foreach ($this->periods as $day => $periods) {
                foreach ($periods as $period) {
                    $this->employee->workPeriods()->create([
                        'weekday' => $day, 'starts_at' => $period['starts_at'], 'ends_at' => $period['ends_at'],
                    ]);
                }
            }
        });
        session()->flash('status', 'Horario semanal guardado correctamente.');
    }

    public function render()
    {
        return view('livewire.people.employee-schedule')
            ->layout('layouts.app', ['title' => 'Horario de '.$this->employee->name]);
    }

    private function authorizeManagement(): void
    {
        abort_unless(auth()->user()?->can('people.manage'), 403);
    }

    private function periodsOverlap(): bool
    {
        $weekMinutes = 7 * 24 * 60;
        $intervals = [];
        foreach ($this->periods as $day => $periods) {
            foreach ($periods as $period) {
                [$startHour, $startMinute] = array_map('intval', explode(':', $period['starts_at']));
                [$endHour, $endMinute] = array_map('intval', explode(':', $period['ends_at']));
                $start = ((int) $day * 1440) + ($startHour * 60) + $startMinute;
                $end = ((int) $day * 1440) + ($endHour * 60) + $endMinute;
                if ($end <= $start) {
                    $end += 1440;
                }
                $intervals[] = [$start, $end];
            }
        }

        foreach ($intervals as $index => [$start, $end]) {
            foreach ($intervals as $otherIndex => [$otherStart, $otherEnd]) {
                if ($index === $otherIndex) {
                    continue;
                }
                foreach ([$otherStart - $weekMinutes, $otherStart, $otherStart + $weekMinutes] as $shiftedStart) {
                    $shiftedEnd = $shiftedStart + ($otherEnd - $otherStart);
                    if ($start < $shiftedEnd && $end > $shiftedStart) {
                        return true;
                    }
                }
            }
        }

        return false;
    }
}
