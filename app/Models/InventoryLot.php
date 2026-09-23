<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryLot extends Model
{
    protected $fillable = [
        'product_id', 'warehouse_id', 'lot_number', 'expiry_date', 'quantity', 'received_at', 'expiry_notified_at',
    ];

    protected function casts(): array
    {
        return [
            'expiry_date' => 'date',
            'quantity' => 'decimal:3',
            'received_at' => 'datetime',
            'expiry_notified_at' => 'datetime',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(StockMovementLot::class);
    }
}
