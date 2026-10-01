<?php

namespace App\Livewire\People;

use App\Services\People\WeeklyScheduleReport;
use Carbon\CarbonImmutable;
use Livewire\Attributes\Url;
use Livewire\Component;

class ScheduleOverview extends Component
{
    #[Url(as: 'semana')]
    public string $week = '';

    public function mount(): void
    {
        $this->week = $this->normalizedWeek();
    }

    public function previousWeek(): void
    {
        $this->week = CarbonImmutable::parse($this->week)->subWeek()->startOfWeek()->toDateString();
    }

    public function nextWeek(): void
    {
        $this->week = CarbonImmutable::parse($this->week)->addWeek()->startOfWeek()->toDateString();
    }

    public function currentWeek(): void
    {
        $this->week = today()->startOfWeek()->toDateString();
    }

    public function updatedWeek(): void
    {
        $this->week = $this->normalizedWeek();
    }

    public function render(WeeklyScheduleReport $report)
    {
        $this->week = $this->normalizedWeek();

        return view('livewire.people.schedule-overview', $report->data($this->week))
            ->layout('layouts.app', ['title' => 'Cuadrante general']);
    }

    private function normalizedWeek(): string
    {
        try {
            return CarbonImmutable::parse($this->week ?: today())->startOfWeek()->toDateString();
        } catch (\Throwable) {
            return today()->startOfWeek()->toDateString();
        }
    }
}
