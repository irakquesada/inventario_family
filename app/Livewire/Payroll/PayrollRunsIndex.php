<?php

namespace App\Livewire\Payroll;

use App\Models\Employee;
use App\Models\EmploymentContract;
use App\Models\PayrollEntry;
use App\Models\PayrollRun;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\WithPagination;

class PayrollRunsIndex extends Component
{
    use WithPagination;

    public string $name = '';

    public string $periodStartsOn = '';

    public string $periodEndsOn = '';

    public string $paymentDate = '';

    public string $notes = '';

    public function mount(): void
    {
        $this->periodStartsOn = today()->startOfMonth()->toDateString();
        $this->periodEndsOn = today()->endOfMonth()->toDateString();
        $this->paymentDate = today()->endOfMonth()->toDateString();
        $this->name = 'Nómina '.ucfirst(today()->translatedFormat('F Y'));
    }

    public function create(): void
    {
        $data = $this->validate(['name' => ['required', 'string', 'max:255'], 'periodStartsOn' => ['required', 'date'], 'periodEndsOn' => ['required', 'date', 'after_or_equal:periodStartsOn'], 'paymentDate' => ['nullable', 'date'], 'notes' => ['nullable', 'string', 'max:5000']]);
        if (PayrollRun::query()->whereDate('period_starts_on', '<=', $data['periodEndsOn'])->whereDate('period_ends_on', '>=', $data['periodStartsOn'])->exists()) {
            throw ValidationException::withMessages(['periodStartsOn' => 'Ya existe un periodo de nómina que se solapa con estas fechas.']);
        }
        $run = DB::transaction(function () use ($data): PayrollRun {
            $run = PayrollRun::query()->create(['reference' => 'NOM-'.now()->format('YmdHis').'-'.str()->upper(str()->random(4)), 'name' => $data['name'], 'period_starts_on' => $data['periodStartsOn'], 'period_ends_on' => $data['periodEndsOn'], 'payment_date' => $data['paymentDate'] ?: null, 'notes' => $data['notes'] ?: null, 'created_by' => auth()->id()]);
            Employee::query()->where('is_active', true)->each(function (Employee $employee) use ($run): void {
                $contract = EmploymentContract::query()->where('employee_id', $employee->id)->where('status', 'active')->whereDate('starts_on', '<=', $run->period_ends_on)->where(fn ($q) => $q->whereNull('ends_on')->orWhereDate('ends_on', '>=', $run->period_starts_on))->latest('starts_on')->first();
                $base = $contract?->annual_gross_salary ? round((float) $contract->annual_gross_salary / max(1, $contract->salary_payments), 2) : 0;
                $entry = PayrollEntry::query()->create(['payroll_run_id' => $run->id, 'employee_id' => $employee->id, 'employment_contract_id' => $contract?->id, 'base_salary' => $base, 'earnings_total' => $base, 'net_total' => $base, 'employer_cost' => $base]);
                if ($base > 0) {
                    $entry->items()->create(['type' => 'earning', 'code' => 'SALARIO_BASE', 'description' => 'Salario base', 'amount' => $base]);
                }
            });

            return $run;
        });
        $this->redirectRoute('payroll.runs.show', $run);
    }

    public function render()
    {
        $yearStart = today()->startOfYear();

        return view('livewire.payroll.payroll-runs-index', [
            'runs' => PayrollRun::query()->withCount('entries')->withSum('entries', 'net_total')->latest('period_starts_on')->paginate(12),
            'yearNet' => PayrollEntry::query()->whereHas('run', fn ($q) => $q->whereDate('period_starts_on', '>=', $yearStart))->sum('net_total'),
            'yearEmployerCost' => PayrollEntry::query()->whereHas('run', fn ($q) => $q->whereDate('period_starts_on', '>=', $yearStart))->sum('employer_cost'),
        ])->layout('layouts.app', ['title' => 'Nóminas']);
    }
}
