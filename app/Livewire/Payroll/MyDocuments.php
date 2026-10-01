<?php

namespace App\Livewire\Payroll;

use App\Models\Employee;
use Livewire\Component;

class MyDocuments extends Component
{
    public function render()
    {
        $employee = Employee::query()->where('user_id', auth()->id())->firstOrFail();

        return view('livewire.payroll.my-documents', [
            'employee' => $employee,
            'contracts' => $employee->employmentContracts()->latest('starts_on')->get(),
            'entries' => $employee->payrollEntries()->with(['run', 'items'])->whereHas('run', fn ($q) => $q->whereIn('status', ['finalized', 'paid']))->latest()->get(),
        ])->layout('layouts.app', ['title' => 'Mis documentos laborales']);
    }
}
