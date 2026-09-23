<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseOrderLine extends Model
{
    protected $fillable = ['purchase_order_id', 'product_id', 'supplier_sku', 'ordered_quantity', 'received_quantity', 'unit_cost', 'notes'];

    protected function casts(): array
    {
        return ['ordered_quantity' => 'decimal:3', 'received_quantity' => 'decimal:3', 'unit_cost' => 'decimal:4'];
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function receiptLines(): HasMany
    {
        return $this->hasMany(PurchaseReceiptLine::class);
    }
}
