<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'category_id', 'brand_id', 'name', 'slug', 'sku', 'barcode', 'description',
        'unit', 'cost', 'price', 'minimum_stock', 'notify_low_stock', 'tracks_lots',
        'requires_expiry', 'expiry_warning_days', 'is_active', 'is_sellable',
        'featured_on_landing',
        'landing_image_path',
    ];

    protected function casts(): array
    {
        return [
            'cost' => 'decimal:4',
            'price' => 'decimal:4',
            'minimum_stock' => 'decimal:3',
            'notify_low_stock' => 'boolean',
            'tracks_lots' => 'boolean',
            'requires_expiry' => 'boolean',
            'expiry_warning_days' => 'integer',
            'is_active' => 'boolean',
            'is_sellable' => 'boolean',
            'featured_on_landing' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function balances(): HasMany
    {
        return $this->hasMany(InventoryBalance::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function suppliers(): BelongsToMany
    {
        return $this->belongsToMany(Supplier::class)
            ->withPivot(['id', 'supplier_sku', 'unit_cost', 'minimum_order_quantity', 'lead_time_days', 'is_preferred', 'notes'])
            ->withTimestamps();
    }

    public function supplierReferences(): HasMany
    {
        return $this->hasMany(ProductSupplier::class);
    }

    public function lots(): HasMany
    {
        return $this->hasMany(InventoryLot::class);
    }

    public function purchaseOrderLines(): HasMany
    {
        return $this->hasMany(PurchaseOrderLine::class);
    }

    public function preferredSupplierReference(): HasOne
    {
        return $this->hasOne(ProductSupplier::class)->where('is_preferred', true);
    }
}
