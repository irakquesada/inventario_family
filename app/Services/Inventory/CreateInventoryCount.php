<?php

namespace App\Services\Inventory;

use App\Models\InventoryCount;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CreateInventoryCount
{
    public function handle(Warehouse $warehouse, User $user, ?string $notes = null): InventoryCount
    {
        return DB::transaction(function () use ($warehouse, $user, $notes): InventoryCount {
            Warehouse::query()->whereKey($warehouse->id)->lockForUpdate()->firstOrFail();

            if (InventoryCount::query()->whereBelongsTo($warehouse)->where('status', 'open')->exists()) {
                throw ValidationException::withMessages([
                    'warehouseId' => 'Este almacén ya tiene un conteo abierto.',
                ]);
            }

            $count = InventoryCount::query()->create([
                'reference' => (string) Str::uuid(),
                'warehouse_id' => $warehouse->id,
                'created_by' => $user->id,
                'status' => 'open',
                'notes' => $notes,
                'started_at' => now(),
            ]);

            Product::query()
                ->where('products.is_active', true)
                ->leftJoin('inventory_balances', function ($join) use ($warehouse): void {
                    $join->on('inventory_balances.product_id', '=', 'products.id')
                        ->where('inventory_balances.warehouse_id', $warehouse->id);
                })
                ->select('products.id', DB::raw('COALESCE(inventory_balances.quantity, 0) as expected_quantity'))
                ->orderBy('products.id')
                ->chunk(500, function ($products) use ($count): void {
                    $now = now();
                    DB::table('inventory_count_items')->insert($products->map(fn ($product) => [
                        'inventory_count_id' => $count->id,
                        'product_id' => $product->id,
                        'expected_quantity' => $product->expected_quantity,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ])->all());
                });

            return $count;
        });
    }
}
