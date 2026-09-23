<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeAbsenceBalance extends Model
{
    protected $fillable = ['employee_id', 'year', 'entitled_days', 'notes'];

    protected function casts(): array
    {
        return ['year' => 'integer', 'entitled_days' => 'decimal:2'];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
