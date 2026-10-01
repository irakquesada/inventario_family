<?php

namespace App\Livewire\People;

use App\Models\AbsenceRequest;
use App\Models\AbsenceType;
use App\Models\Employee;
use App\Models\EmployeeAbsenceBalance;
use App\Services\People\ReviewAbsenceRequest;
use Carbon\CarbonImmutable;
use Illuminate\Validation\Rule;
use Livewire\Component;

class AbsencesAdmin extends Component
{
    public string $status = 'pending';

    public string $employeeId = '';

    public string $typeName = '';

    public string $typeCode = '';

    public bool $deductsBalance = false;

    public bool $requiresAttachment = false;

    public bool $isPaid = true;

    public bool $typeActive = true;

    public string $balanceEmployeeId = '';

    public string $balanceYear = '';

    public string $entitledDays = '';

    public string $balanceNotes = '';

    public ?int $reviewingId = null;

    public string $decision = 'approved';

    public string $reviewComment = '';

    public bool $flexibleApproval = false;

    public string $calendarMonth = '';

    public function mount(): void
    {
        $this->balanceYear = (string) now()->year;
        $this->calendarMonth = now()->format('Y-m');
    }

    public function previousMonth(): void
    {
        $this->calendarMonth = CarbonImmutable::createFromFormat('Y-m', $this->calendarMonth)->subMonth()->format('Y-m');
    }

    public function nextMonth(): void
    {
        $this->calendarMonth = CarbonImmutable::createFromFormat('Y-m', $this->calendarMonth)->addMonth()->format('Y-m');
    }

    public function saveType(): void
    {
        $this->authorizeManagement();
        $data = $this->validate([
            'typeName' => ['required', 'string', 'max:255', 'unique:absence_types,name'], 'typeCode' => ['required', 'string', 'max:40', 'unique:absence_types,code'],
            'deductsBalance' => ['boolean'], 'requiresAttachment' => ['boolean'], 'isPaid' => ['boolean'], 'typeActive' => ['boolean'],
        ]);
        AbsenceType::query()->create(['name' => $data['typeName'], 'code' => str($data['typeCode'])->upper(), 'deducts_balance' => $data['deductsBalance'], 'requires_attachment' => $data['requiresAttachment'], 'is_paid' => $data['isPaid'], 'is_active' => $data['typeActive']]);
        $this->reset(['typeName', 'typeCode', 'deductsBalance', 'requiresAttachment']);
        $this->isPaid = true;
        $this->typeActive = true;
    }

    public function toggleType(int $id): void
    {
        $this->authorizeManagement();
        $type = AbsenceType::query()->findOrFail($id);
        $type->update(['is_active' => ! $type->is_active]);
    }

    public function saveBalance(): void
    {
        $this->authorizeManagement();
        $data = $this->validate([
            'balanceEmployeeId' => ['required', 'integer', 'exists:employees,id'], 'balanceYear' => ['required', 'integer', 'min:2020', 'max:2100'],
            'entitledDays' => ['required', 'numeric', 'min:0', 'max:366'], 'balanceNotes' => ['nullable', 'string', 'max:1000'],
        ]);
        EmployeeAbsenceBalance::query()->updateOrCreate(['employee_id' => $data['balanceEmployeeId'], 'year' => $data['balanceYear']], ['entitled_days' => $data['entitledDays'], 'notes' => $data['balanceNotes'] ?: null]);
        session()->flash('status', 'Saldo anual actualizado.');
    }

    public function openReview(int $id, string $decision): void
    {
        $this->authorizeManagement();
        AbsenceRequest::query()->where('status', 'pending')->findOrFail($id);
        $this->reviewingId = $id;
        $this->decision = $decision;
        $this->reviewComment = '';
        $this->flexibleApproval = false;
    }

    public function review(ReviewAbsenceRequest $reviewer): void
    {
        $this->authorizeManagement();
        $data = $this->validate([
            'reviewingId' => ['required', 'exists:absence_requests,id'], 'decision' => ['required', Rule::in(['approved', 'rejected'])],
            'reviewComment' => [Rule::requiredIf($this->decision === 'approved' && $this->flexibleApproval), 'nullable', 'string', 'max:1000'],
            'flexibleApproval' => ['boolean'],
        ]);
        $reviewer->handle(AbsenceRequest::query()->findOrFail($data['reviewingId']), auth()->user(), $data['decision'], $data['reviewComment'] ?: null, $data['flexibleApproval']);
        $this->reset(['reviewingId', 'reviewComment', 'flexibleApproval']);
        session()->flash('status', 'Solicitud revisada correctamente.');
    }

    public function render()
    {
        $requests = AbsenceRequest::query()->with(['employee.jobPosition', 'type', 'reviewer'])->when($this->status !== 'all', fn ($q) => $q->where('status', $this->status))->when($this->employeeId !== '', fn ($q) => $q->where('employee_id', $this->employeeId))->orderByDesc('created_at')->get();

        $month = CarbonImmutable::createFromFormat('Y-m', $this->calendarMonth)->startOfMonth();
        $calendarStart = $month->startOfWeek();
        $calendarRequests = AbsenceRequest::query()->with(['employee', 'type'])
            ->whereIn('status', ['pending', 'approved'])
            ->whereDate('starts_on', '<=', $calendarStart->addDays(41))
            ->whereDate('ends_on', '>=', $calendarStart)->get();
        $calendarDays = collect(range(0, 41))->map(function (int $offset) use ($calendarStart, $calendarRequests, $month): array {
            $date = $calendarStart->addDays($offset);

            return [
                'date' => $date, 'in_month' => $date->month === $month->month,
                'requests' => $calendarRequests->filter(fn ($request) => $request->starts_on->lte($date) && $request->ends_on->gte($date))->values(),
            ];
        });

        $balances = EmployeeAbsenceBalance::query()->whereIn('employee_id', $requests->pluck('employee_id'))->get()->keyBy(fn ($balance) => $balance->employee_id.'-'.$balance->year);
        $usedByEmployeeYear = AbsenceRequest::query()->where('status', 'approved')->whereHas('type', fn ($query) => $query->where('deducts_balance', true))->get()
            ->groupBy(fn ($request) => $request->employee_id.'-'.$request->starts_on->year)->map->sum('requested_days');
        $balanceFor = function (AbsenceRequest $request) use ($balances, $usedByEmployeeYear): array {
            $key = $request->employee_id.'-'.$request->starts_on->year;
            $entitled = (float) ($balances->get($key)?->entitled_days ?? 0);
            $used = (float) ($usedByEmployeeYear->get($key) ?? 0);

            return ['entitled' => $entitled, 'used' => $used, 'available' => round($entitled - $used, 2)];
        };
        $reviewingRequest = $this->reviewingId ? $requests->firstWhere('id', $this->reviewingId) ?? AbsenceRequest::query()->with(['employee', 'type'])->find($this->reviewingId) : null;

        return view('livewire.people.absences-admin', [
            'requests' => $requests, 'employees' => Employee::query()->orderBy('name')->get(),
            'types' => AbsenceType::query()->orderBy('name')->get(), 'calendarDays' => $calendarDays, 'month' => $month,
            'balanceFor' => $balanceFor, 'reviewingRequest' => $reviewingRequest,
            'reviewingBalance' => $reviewingRequest ? $balanceFor($reviewingRequest) : null,
        ])->layout('layouts.app', ['title' => 'Ausencias y vacaciones']);
    }

    private function authorizeManagement(): void
    {
        abort_unless(auth()->user()?->can('people.manage'), 403);
    }
}
