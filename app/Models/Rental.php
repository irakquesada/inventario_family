<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Rental extends Model
{
    protected $fillable = ['reference', 'number', 'product_id', 'warehouse_id', 'rental_rate_id', 'created_by', 'returned_by', 'status', 'customer_name', 'customer_email', 'customer_phone', 'quantity', 'started_at', 'planned_ends_at', 'returned_at', 'rate_price', 'billing_unit', 'unit_minutes', 'minimum_units', 'grace_minutes', 'billed_units', 'estimated_total', 'final_total', 'notes', 'return_notes'];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:3', 'started_at' => 'datetime', 'planned_ends_at' => 'datetime', 'returned_at' => 'datetime', 'rate_price' => 'decimal:2', 'estimated_total' => 'decimal:2', 'final_total' => 'decimal:2'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function rate(): BelongsTo
    {
        return $this->belongsTo(RentalRate::class, 'rental_rate_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function returner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'returned_by');
    }
}
