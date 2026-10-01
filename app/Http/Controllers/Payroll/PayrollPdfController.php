<?php

namespace App\Http\Controllers\Payroll;

use App\Http\Controllers\Controller;
use App\Models\EmploymentContract;
use App\Models\PayrollEntry;
use App\Models\PayrollRun;
use App\Models\SiteSetting;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

class PayrollPdfController extends Controller
{
    public function payslip(PayrollEntry $entry): Response
    {
        $own = $entry->employee()->where('user_id', auth()->id())->exists();
        abort_unless($own || auth()->user()?->can('payroll.view'), 403);
        abort_unless($entry->run->status !== 'draft' || auth()->user()?->can('payroll.manage'), 403);
        $entry->load(['employee.jobPosition', 'contract', 'items', 'run']);

        return Pdf::loadView('pdf.payslip', ['entry' => $entry, 'company' => SiteSetting::query()->first(), 'generatedAt' => now()])->setPaper('a4')->download("nomina-{$entry->run->reference}-{$entry->employee->employee_number}.pdf");
    }

    public function summary(PayrollRun $payrollRun): Response
    {
        $payrollRun->load(['entries.employee.jobPosition']);

        return Pdf::loadView('pdf.payroll-summary', ['run' => $payrollRun, 'company' => SiteSetting::query()->first(), 'generatedAt' => now()])->setPaper('a4', 'landscape')->download("resumen-{$payrollRun->reference}.pdf");
    }

    public function contract(EmploymentContract $contract): Response
    {
        $own = $contract->employee()->where('user_id', auth()->id())->exists();
        abort_unless($own || auth()->user()?->can('payroll.view'), 403);
        $contract->load('employee.jobPosition');
        if (! auth()->user()?->can('payroll.manage')) {
            $contract->notes = null;
        }

        return Pdf::loadView('pdf.contract-summary', ['contract' => $contract, 'company' => SiteSetting::query()->first(), 'showInternalNotes' => auth()->user()?->can('payroll.manage'), 'generatedAt' => now()])->setPaper('a4')->download("ficha-{$contract->reference}.pdf");
    }
}
