<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PayrollRun extends Model
{
    protected $fillable = ['reference', 'name', 'period_starts_on', 'period_ends_on', 'payment_date', 'status', 'notes', 'created_by', 'finalized_by', 'finalized_at', 'paid_at'];

    protected function casts(): array
    {
        return ['period_starts_on' => 'date', 'period_ends_on' => 'date', 'payment_date' => 'date', 'finalized_at' => 'datetime', 'paid_at' => 'datetime'];
    }

    public function entries(): HasMany
    {
        return $this->hasMany(PayrollEntry::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
