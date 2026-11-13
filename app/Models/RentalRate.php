<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RentalRate extends Model
{
    protected $fillable = ['product_id', 'name', 'billing_unit', 'unit_minutes', 'price', 'minimum_units', 'grace_minutes', 'is_active'];

    protected function casts(): array
    {
        return ['price' => 'decimal:2', 'minimum_units' => 'integer', 'grace_minutes' => 'integer', 'is_active' => 'boolean'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function rentals(): HasMany
    {
        return $this->hasMany(Rental::class);
    }
}
