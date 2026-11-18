<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Rental extends Model
{
    use SoftDeletes;

    protected $fillable = ['reference', 'number', 'product_id', 'warehouse_id', 'rental_rate_id', 'created_by', 'returned_by', 'confirmed_by', 'status', 'source', 'customer_name', 'customer_email', 'customer_phone', 'quantity', 'started_at', 'planned_ends_at', 'returned_at', 'confirmed_at', 'confirmation_sent_at', 'rate_price', 'billing_unit', 'unit_minutes', 'minimum_units', 'grace_minutes', 'billed_units', 'estimated_total', 'final_total', 'notes', 'return_notes'];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:3', 'started_at' => 'datetime', 'planned_ends_at' => 'datetime', 'returned_at' => 'datetime', 'confirmed_at' => 'datetime', 'confirmation_sent_at' => 'datetime', 'rate_price' => 'decimal:2', 'estimated_total' => 'decimal:2', 'final_total' => 'decimal:2'];
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

    public function confirmer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(RentalPayment::class);
    }

    public function amountDue(): float
    {
        $total = $this->status === 'returned' ? $this->final_total : $this->estimated_total;

        return max(0, round((float) ($total ?? 0) - (float) ($this->payments_sum_amount ?? $this->payments()->sum('amount')), 2));
    }

    public function paymentStatus(): string
    {
        $paid = (float) ($this->payments_sum_amount ?? $this->payments()->sum('amount'));
        $total = (float) (($this->status === 'returned' ? $this->final_total : $this->estimated_total) ?? 0);
        if ($paid <= 0) {
            return 'pending';
        }
        if ($total > 0 && $paid + 0.001 >= $total) {
            return 'paid';
        }

        return 'partial';
    }
}
