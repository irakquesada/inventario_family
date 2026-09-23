<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryCountItem extends Model
{
    protected $fillable = [
        'inventory_count_id', 'product_id', 'expected_quantity', 'counted_quantity',
        'counted_by', 'counted_at', 'notes',
        'reconciliation_lot_number', 'reconciliation_expiry_date',
    ];

    protected function casts(): array
    {
        return [
            'expected_quantity' => 'decimal:3',
            'counted_quantity' => 'decimal:3',
            'counted_at' => 'datetime',
            'reconciliation_expiry_date' => 'date',
        ];
    }

    public function inventoryCount(): BelongsTo
    {
        return $this->belongsTo(InventoryCount::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function counter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'counted_by');
    }
}
