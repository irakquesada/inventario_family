<?php

namespace App\Livewire\Payroll;

use App\Models\PayrollEntry;
use App\Models\PayrollRun;
use App\Services\Payroll\RecalculatePayrollEntry;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class PayrollRunShow extends Component
{
    public PayrollRun $payrollRun;

    public ?int $editingEntryId = null;

    public string $itemType = 'earning';

    public string $itemCode = '';

    public string $itemDescription = '';

    public string $itemAmount = '';

    public string $employerCost = '';

    public string $entryNotes = '';

    public function editEntry(int $entryId): void
    {
        $entry = $this->entry($entryId);
        $this->editingEntryId = $entry->id;
        $this->employerCost = (string) $entry->employer_cost;
        $this->entryNotes = $entry->notes ?? '';
        $this->reset(['itemCode', 'itemDescription', 'itemAmount']);
        $this->itemType = 'earning';
    }

    public function saveEntry(): void
    {
        $this->ensureDraft();
        $entry = $this->entry((int) $this->editingEntryId);
        $data = $this->validate(['employerCost' => ['required', 'numeric', 'min:0'], 'entryNotes' => ['nullable', 'string', 'max:3000']]);
        $entry->update(['employer_cost' => $data['employerCost'], 'notes' => $data['entryNotes'] ?: null]);
        session()->flash('status', 'Datos del recibo actualizados.');
    }

    public function addItem(RecalculatePayrollEntry $calculator): void
    {
        $this->ensureDraft();
        $entry = $this->entry((int) $this->editingEntryId);
        $data = $this->validate([
            'itemType' => ['required', Rule::in(['earning', 'deduction'])], 'itemCode' => ['nullable', 'string', 'max:50'],
            'itemDescription' => ['required', 'string', 'max:255'], 'itemAmount' => ['required', 'numeric', 'min:0.01', 'max:999999999.99'],
        ]);
        $entry->items()->create(['type' => $data['itemType'], 'code' => $data['itemCode'] ?: null, 'description' => $data['itemDescription'], 'amount' => $data['itemAmount']]);
        $calculator->handle($entry);
        $this->reset(['itemCode', 'itemDescription', 'itemAmount']);
    }

    public function deleteItem(int $itemId, RecalculatePayrollEntry $calculator): void
    {
        $this->ensureDraft();
        $entry = $this->entry((int) $this->editingEntryId);
        $entry->items()->whereKey($itemId)->delete();
        $calculator->handle($entry);
    }

    public function finalize(): void
    {
        $this->ensureDraft();
        if ($this->payrollRun->entries()->count() === 0) {
            throw ValidationException::withMessages(['run' => 'No se puede cerrar un periodo sin recibos.']);
        }
        DB::transaction(fn () => $this->payrollRun->update(['status' => 'finalized', 'finalized_by' => auth()->id(), 'finalized_at' => now()]));
        session()->flash('status', 'Periodo cerrado. Los importes han quedado bloqueados.');
    }

    public function markPaid(): void
    {
        abort_unless($this->payrollRun->status === 'finalized', 422);
        $this->payrollRun->update(['status' => 'paid', 'paid_at' => now()]);
        session()->flash('status', 'Periodo marcado como pagado.');
    }

    public function render()
    {
        $this->payrollRun->load(['entries.employee.jobPosition', 'entries.items']);

        return view('livewire.payroll.payroll-run-show', ['totals' => [
            'earnings' => $this->payrollRun->entries->sum('earnings_total'), 'deductions' => $this->payrollRun->entries->sum('deductions_total'),
            'net' => $this->payrollRun->entries->sum('net_total'), 'cost' => $this->payrollRun->entries->sum('employer_cost'),
        ]])->layout('layouts.app', ['title' => $this->payrollRun->name]);
    }

    private function ensureDraft(): void
    {
        abort_unless($this->payrollRun->status === 'draft', 422);
    }

    private function entry(int $id): PayrollEntry
    {
        return $this->payrollRun->entries()->findOrFail($id);
    }
}
