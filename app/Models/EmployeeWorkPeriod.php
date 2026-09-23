<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeWorkPeriod extends Model
{
    protected $fillable = ['employee_id', 'weekday', 'starts_at', 'ends_at'];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
