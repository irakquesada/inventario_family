<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollEntryItem extends Model
{
    protected $fillable = ['payroll_entry_id', 'type', 'code', 'description', 'quantity', 'rate', 'amount', 'sort_order'];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:2', 'rate' => 'decimal:4', 'amount' => 'decimal:2'];
    }

    public function entry(): BelongsTo
    {
        return $this->belongsTo(PayrollEntry::class, 'payroll_entry_id');
    }
}
