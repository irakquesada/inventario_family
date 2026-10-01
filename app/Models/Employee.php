<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Employee extends Model
{
    protected $fillable = ['user_id', 'job_position_id', 'employee_number', 'name', 'email', 'phone', 'tax_id', 'social_security_number', 'postal_address', 'iban', 'position', 'hired_on', 'is_active', 'notes'];

    protected function casts(): array
    {
        return ['hired_on' => 'date', 'is_active' => 'boolean', 'tax_id' => 'encrypted', 'social_security_number' => 'encrypted', 'postal_address' => 'encrypted', 'iban' => 'encrypted'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function jobPosition(): BelongsTo
    {
        return $this->belongsTo(JobPosition::class);
    }

    public function workPeriods(): HasMany
    {
        return $this->hasMany(EmployeeWorkPeriod::class)->orderBy('weekday')->orderBy('starts_at');
    }

    public function timeEntries(): HasMany
    {
        return $this->hasMany(TimeEntry::class)->latest('clocked_in_at');
    }

    public function absenceRequests(): HasMany
    {
        return $this->hasMany(AbsenceRequest::class);
    }

    public function absenceBalances(): HasMany
    {
        return $this->hasMany(EmployeeAbsenceBalance::class);
    }

    public function employmentContracts(): HasMany
    {
        return $this->hasMany(EmploymentContract::class);
    }

    public function payrollEntries(): HasMany
    {
        return $this->hasMany(PayrollEntry::class);
    }
}
