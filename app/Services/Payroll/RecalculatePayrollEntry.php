<?php

namespace App\Services\Payroll;

use App\Models\PayrollEntry;

class RecalculatePayrollEntry
{
    public function handle(PayrollEntry $entry): PayrollEntry
    {
        $earnings = (float) $entry->items()->where('type', 'earning')->sum('amount');
        $deductions = (float) $entry->items()->where('type', 'deduction')->sum('amount');
        $entry->update([
            'earnings_total' => round($earnings, 2),
            'deductions_total' => round($deductions, 2),
            'net_total' => round($earnings - $deductions, 2),
        ]);

        return $entry->refresh();
    }
}
