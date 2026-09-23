<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Supplier extends Model
{
    protected $fillable = [
        'name', 'tax_id', 'contact_name', 'email', 'phone', 'address', 'notes', 'is_active',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class)
            ->withPivot(['id', 'supplier_sku', 'unit_cost', 'minimum_order_quantity', 'lead_time_days', 'is_preferred', 'notes'])
            ->withTimestamps();
    }

    public function productReferences(): HasMany
    {
        return $this->hasMany(ProductSupplier::class);
    }

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class);
    }
}
