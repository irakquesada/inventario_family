<?php

namespace App\Livewire\Payroll;

use App\Models\Employee;
use App\Models\EmploymentContract;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

class ContractsIndex extends Component
{
    use WithFileUploads, WithPagination;

    public ?int $editingId = null;

    public string $employeeId = '';

    public string $reference = '';

    public string $contractType = 'indefinite';

    public string $status = 'draft';

    public string $startsOn = '';

    public string $endsOn = '';

    public string $probationEndsOn = '';

    public string $weeklyHours = '';

    public string $annualGrossSalary = '';

    public string $salaryPayments = '12';

    public string $collectiveAgreement = '';

    public string $workplace = '';

    public string $notes = '';

    public $document;

    public string $search = '';

    public string $statusFilter = '';

    public function mount(): void
    {
        $this->startsOn = today()->toDateString();
        $this->reference = $this->nextReference();
    }

    public function save(): void
    {
        abort_unless(auth()->user()?->can('payroll.manage'), 403);
        $data = $this->validate([
            'employeeId' => ['required', 'exists:employees,id'],
            'reference' => ['required', 'string', 'max:50', Rule::unique('employment_contracts', 'reference')->ignore($this->editingId)],
            'contractType' => ['required', Rule::in(['indefinite', 'fixed_term', 'training', 'seasonal', 'temporary', 'other'])],
            'status' => ['required', Rule::in(['draft', 'active', 'expired', 'terminated'])],
            'startsOn' => ['required', 'date'], 'endsOn' => ['nullable', 'date', 'after_or_equal:startsOn'],
            'probationEndsOn' => ['nullable', 'date', 'after_or_equal:startsOn'],
            'weeklyHours' => ['nullable', 'numeric', 'min:0.01', 'max:168'],
            'annualGrossSalary' => ['nullable', 'numeric', 'min:0', 'max:999999999.99'],
            'salaryPayments' => ['required', 'integer', 'min:1', 'max:24'],
            'collectiveAgreement' => ['nullable', 'string', 'max:255'], 'workplace' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'], 'document' => ['nullable', 'file', 'mimes:pdf', 'max:10240'],
        ]);
        $contract = $this->editingId ? EmploymentContract::query()->findOrFail($this->editingId) : new EmploymentContract(['created_by' => auth()->id()]);
        $path = $contract->document_path;
        $name = $contract->document_name;
        if ($this->document) {
            if ($path) {
                Storage::disk('local')->delete($path);
            }
            $path = $this->document->store('contracts', 'local');
            $name = $this->document->getClientOriginalName();
        }
        $contract->fill([
            'employee_id' => $data['employeeId'], 'reference' => str($data['reference'])->upper(),
            'contract_type' => $data['contractType'], 'status' => $data['status'], 'starts_on' => $data['startsOn'],
            'ends_on' => $data['endsOn'] ?: null, 'probation_ends_on' => $data['probationEndsOn'] ?: null,
            'weekly_hours' => $data['weeklyHours'] ?: null, 'annual_gross_salary' => $data['annualGrossSalary'] ?: null,
            'salary_payments' => $data['salaryPayments'], 'collective_agreement' => $data['collectiveAgreement'] ?: null,
            'workplace' => $data['workplace'] ?: null, 'notes' => $data['notes'] ?: null,
            'document_path' => $path, 'document_name' => $name, 'updated_by' => auth()->id(),
        ])->save();
        $this->resetForm();
        session()->flash('status', 'Contrato guardado y auditado correctamente.');
    }

    public function edit(int $id): void
    {
        $contract = EmploymentContract::query()->findOrFail($id);
        $this->editingId = $contract->id;
        $this->employeeId = (string) $contract->employee_id;
        $this->reference = $contract->reference;
        $this->contractType = $contract->contract_type;
        $this->status = $contract->status;
        $this->startsOn = $contract->starts_on->toDateString();
        $this->endsOn = $contract->ends_on?->toDateString() ?? '';
        $this->probationEndsOn = $contract->probation_ends_on?->toDateString() ?? '';
        $this->weeklyHours = (string) ($contract->weekly_hours ?? '');
        $this->annualGrossSalary = (string) ($contract->annual_gross_salary ?? '');
        $this->salaryPayments = (string) $contract->salary_payments;
        $this->collectiveAgreement = $contract->collective_agreement ?? '';
        $this->workplace = $contract->workplace ?? '';
        $this->notes = $contract->notes ?? '';
    }

    public function resetForm(): void
    {
        $this->reset(['editingId', 'employeeId', 'endsOn', 'probationEndsOn', 'weeklyHours', 'annualGrossSalary', 'collectiveAgreement', 'workplace', 'notes', 'document']);
        $this->contractType = 'indefinite';
        $this->status = 'draft';
        $this->startsOn = today()->toDateString();
        $this->salaryPayments = '12';
        $this->reference = $this->nextReference();
        $this->resetValidation();
    }

    public function render()
    {
        return view('livewire.payroll.contracts-index', [
            'employees' => Employee::query()->where('is_active', true)->orderBy('name')->get(),
            'contracts' => EmploymentContract::query()->with(['employee.jobPosition'])->when($this->search, fn ($q) => $q->where(fn ($q) => $q->where('reference', 'like', "%{$this->search}%")->orWhereHas('employee', fn ($q) => $q->where('name', 'like', "%{$this->search}%"))))->when($this->statusFilter, fn ($q) => $q->where('status', $this->statusFilter))->latest('starts_on')->paginate(15),
            'activeCount' => EmploymentContract::query()->where('status', 'active')->count(),
            'draftCount' => EmploymentContract::query()->where('status', 'draft')->count(),
            'expiringCount' => EmploymentContract::query()->where('status', 'active')->whereBetween('ends_on', [today(), today()->addDays(30)])->count(),
        ])->layout('layouts.app', ['title' => 'Contratos']);
    }

    private function nextReference(): string
    {
        return 'CTR-'.now()->format('Ym').'-'.str_pad((string) (EmploymentContract::query()->count() + 1), 4, '0', STR_PAD_LEFT);
    }
}
