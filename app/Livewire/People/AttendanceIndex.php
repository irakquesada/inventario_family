<?php

namespace App\Livewire\People;

use App\Models\Employee;
use App\Models\TimeEntry;
use App\Services\People\AttendanceReport;
use App\Services\People\CorrectTimeEntry;
use App\Services\People\ManageTimeEntry;
use Carbon\CarbonImmutable;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Url;
use Livewire\Component;

class AttendanceIndex extends Component
{
    #[Url]
    public string $from = '';

    #[Url]
    public string $to = '';

    #[Url]
    public string $employeeId = '';

    #[Url]
    public string $status = 'all';

    public ?int $correctingId = null;

    public string $correctionIn = '';

    public string $correctionOut = '';

    public string $correctionReason = '';

    public bool $creatingEntry = false;

    public string $entryEmployeeId = '';

    public string $entryIn = '';

    public string $entryOut = '';

    public string $entryReason = '';

    public ?int $deletingId = null;

    public string $deletionReason = '';

    public function mount(): void
    {
        if ($this->from === '') {
            $this->from = now()->startOfWeek()->toDateString();
        }
        if ($this->to === '') {
            $this->to = now()->endOfWeek()->toDateString();
        }
    }

    public function updated($property): void
    {
        if (in_array($property, ['from', 'to', 'employeeId', 'status'], true)) {
            $this->validateFilters();
        }
    }

    public function editEntry(int $id): void
    {
        $this->authorizeManagement();
        $entry = TimeEntry::query()->findOrFail($id);
        $this->correctingId = $entry->id;
        $this->correctionIn = $entry->clocked_in_at->format('Y-m-d\TH:i');
        $this->correctionOut = $entry->clocked_out_at?->format('Y-m-d\TH:i') ?? '';
        $this->correctionReason = '';
        $this->resetValidation();
    }

    public function openCreateEntry(): void
    {
        $this->authorizeManagement();
        $this->creatingEntry = true;
        $this->entryEmployeeId = $this->employeeId;
        $this->entryIn = now()->format('Y-m-d\TH:i');
        $this->entryOut = '';
        $this->entryReason = '';
        $this->resetValidation();
    }

    public function createEntry(ManageTimeEntry $manager): void
    {
        $this->authorizeManagement();
        $data = $this->validate([
            'entryEmployeeId' => ['required', 'integer', 'exists:employees,id'],
            'entryIn' => ['required', 'date_format:Y-m-d\TH:i'],
            'entryOut' => ['nullable', 'date_format:Y-m-d\TH:i'],
            'entryReason' => ['required', 'string', 'min:5', 'max:1000'],
        ]);
        $manager->create(
            Employee::query()->findOrFail($data['entryEmployeeId']), auth()->user(),
            $data['entryIn'], $data['entryOut'] ?: null, $data['entryReason'], request()->ip(),
        );
        $this->cancelCreateEntry();
        session()->flash('status', 'Fichaje añadido manualmente y auditado.');
    }

    public function cancelCreateEntry(): void
    {
        $this->reset(['creatingEntry', 'entryEmployeeId', 'entryIn', 'entryOut', 'entryReason']);
        $this->resetValidation();
    }

    public function beginDelete(int $id): void
    {
        $this->authorizeManagement();
        $this->deletingId = TimeEntry::query()->findOrFail($id)->id;
        $this->deletionReason = '';
    }

    public function deleteEntry(ManageTimeEntry $manager): void
    {
        $this->authorizeManagement();
        $data = $this->validate([
            'deletingId' => ['required', 'integer', 'exists:time_entries,id'],
            'deletionReason' => ['required', 'string', 'min:5', 'max:1000'],
        ]);
        $manager->delete(TimeEntry::query()->findOrFail($data['deletingId']), auth()->user(), $data['deletionReason'], request()->ip());
        $this->reset(['deletingId', 'deletionReason']);
        session()->flash('status', 'Fichaje anulado. El registro original y el motivo se conservan en auditoría.');
    }

    public function saveCorrection(CorrectTimeEntry $corrector): void
    {
        $this->authorizeManagement();
        $data = $this->validate([
            'correctingId' => ['required', 'integer', 'exists:time_entries,id'],
            'correctionIn' => ['required', 'date_format:Y-m-d\TH:i'],
            'correctionOut' => ['nullable', 'date_format:Y-m-d\TH:i'],
            'correctionReason' => ['required', 'string', 'min:5', 'max:1000'],
        ]);
        $corrector->handle(
            TimeEntry::query()->findOrFail($data['correctingId']), auth()->user(),
            $data['correctionIn'], $data['correctionOut'] ?: null, $data['correctionReason'], request()->ip(),
        );
        $this->cancelCorrection();
        session()->flash('status', 'Fichaje corregido y modificación auditada.');
    }

    public function cancelCorrection(): void
    {
        $this->reset(['correctingId', 'correctionIn', 'correctionOut', 'correctionReason']);
        $this->resetValidation();
    }

    public function render(AttendanceReport $report)
    {
        $this->validateFilters();
        $rows = $report->rows($this->from, $this->to, $this->employeeId !== '' ? (int) $this->employeeId : null, $this->status);

        return view('livewire.people.attendance-index', [
            'rows' => $rows,
            'employees' => Employee::query()->orderBy('name')->get(),
            'plannedMinutes' => $rows->sum('planned_minutes'), 'workedMinutes' => $rows->sum('worked_minutes'),
            'incidentCount' => $rows->filter(fn ($row) => $row['incidents'] !== [])->count(),
        ])->layout('layouts.app', ['title' => 'Control de asistencia']);
    }

    private function validateFilters(): void
    {
        $this->validate([
            'from' => ['required', 'date'], 'to' => ['required', 'date', 'after_or_equal:from'],
            'employeeId' => ['nullable', 'integer', 'exists:employees,id'],
            'status' => ['required', Rule::in(['all', 'ok', 'missing', 'open', 'late', 'incomplete', 'unplanned', 'corrected', 'excused'])],
        ]);
        if (CarbonImmutable::parse($this->from)->diffInDays(CarbonImmutable::parse($this->to)) > 62) {
            throw ValidationException::withMessages(['to' => 'El intervalo máximo de consulta es de 62 días.']);
        }
    }

    private function authorizeManagement(): void
    {
        abort_unless(auth()->user()?->can('people.manage'), 403);
    }
}
