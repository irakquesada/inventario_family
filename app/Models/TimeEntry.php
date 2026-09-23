<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TimeEntry extends Model
{
    protected $fillable = ['employee_id', 'recorded_by', 'clocked_in_at', 'clocked_out_at', 'source', 'ip_address', 'notes'];

    protected function casts(): array
    {
        return ['clocked_in_at' => 'datetime', 'clocked_out_at' => 'datetime'];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function adjustments(): HasMany
    {
        return $this->hasMany(TimeEntryAdjustment::class);
    }
}
