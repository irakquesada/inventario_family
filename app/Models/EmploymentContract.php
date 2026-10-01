<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmploymentContract extends Model
{
    protected $fillable = ['employee_id', 'reference', 'contract_type', 'status', 'starts_on', 'ends_on', 'probation_ends_on', 'weekly_hours', 'annual_gross_salary', 'salary_payments', 'collective_agreement', 'workplace', 'notes', 'document_path', 'document_name', 'created_by', 'updated_by'];

    protected function casts(): array
    {
        return ['starts_on' => 'date', 'ends_on' => 'date', 'probation_ends_on' => 'date', 'weekly_hours' => 'decimal:2', 'annual_gross_salary' => 'decimal:2'];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
