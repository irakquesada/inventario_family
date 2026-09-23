<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductSupplier extends Model
{
    protected $table = 'product_supplier';

    protected $fillable = [
        'product_id', 'supplier_id', 'supplier_sku', 'unit_cost',
        'minimum_order_quantity', 'lead_time_days', 'is_preferred', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'unit_cost' => 'decimal:4',
            'minimum_order_quantity' => 'decimal:3',
            'lead_time_days' => 'integer',
            'is_preferred' => 'boolean',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }
}
