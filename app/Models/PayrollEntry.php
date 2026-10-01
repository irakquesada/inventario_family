<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PayrollEntry extends Model
{
    protected $fillable = ['payroll_run_id', 'employee_id', 'employment_contract_id', 'base_salary', 'earnings_total', 'deductions_total', 'net_total', 'employer_cost', 'notes'];

    protected function casts(): array
    {
        return ['base_salary' => 'decimal:2', 'earnings_total' => 'decimal:2', 'deductions_total' => 'decimal:2', 'net_total' => 'decimal:2', 'employer_cost' => 'decimal:2'];
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class, 'payroll_run_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(EmploymentContract::class, 'employment_contract_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PayrollEntryItem::class)->orderBy('sort_order')->orderBy('id');
    }
}
