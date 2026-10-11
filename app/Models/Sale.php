<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sale extends Model
{
    public const STATUSES = ['draft', 'pending', 'confirmed', 'preparing', 'ready', 'completed', 'cancelled', 'refunded'];

    protected $fillable = ['reference', 'number', 'warehouse_id', 'user_id', 'status', 'channel', 'fulfilment_method', 'pickup_date', 'pickup_time', 'payment_method', 'customer_name', 'customer_email', 'customer_phone', 'subtotal', 'discount', 'total', 'notes', 'confirmed_at', 'completed_at', 'cancelled_at', 'cancelled_by', 'cancellation_reason'];

    protected function casts(): array
    {
        return ['subtotal' => 'decimal:2', 'discount' => 'decimal:2', 'total' => 'decimal:2', 'pickup_date' => 'date', 'confirmed_at' => 'datetime', 'completed_at' => 'datetime', 'cancelled_at' => 'datetime'];
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(SaleStatusHistory::class);
    }
}
