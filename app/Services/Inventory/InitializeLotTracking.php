<?php

namespace App\Services\Inventory;

use App\Models\InventoryBalance;
use App\Models\InventoryLot;
use App\Models\Product;
use App\Models\StockMovementLot;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class InitializeLotTracking
{
    public function __construct(private RecordStockMovement $recorder) {}

    public function handle(Product $product, User $user, string $lotNumber, ?string $expiryDate = null): void
    {
        $lotNumber = Str::upper(trim($lotNumber));
        $expiry = $expiryDate ? now()->parse($expiryDate)->toDateString() : null;

        if ($lotNumber === '') {
            throw ValidationException::withMessages(['initialLotNumber' => 'Indica el lote que representa las existencias actuales.']);
        }
        if ($product->requires_expiry && $expiry === null) {
            throw ValidationException::withMessages(['initialExpiryDate' => 'La caducidad es obligatoria para este producto.']);
        }

        DB::transaction(function () use ($product, $user, $lotNumber, $expiry): void {
            $product = Product::query()->whereKey($product->id)->lockForUpdate()->firstOrFail();
            $balances = InventoryBalance::query()
                ->with('warehouse')
                ->whereBelongsTo($product)
                ->where('quantity', '>', 0)
                ->orderBy('warehouse_id')
                ->lockForUpdate()
                ->get();

            if ($product->lots()->where('quantity', '>', 0)->exists()) {
                throw ValidationException::withMessages(['tracksLots' => 'El producto ya tiene existencias asignadas a lotes.']);
            }

            foreach ($balances as $balance) {
                $lot = InventoryLot::query()->create([
                    'product_id' => $product->id,
                    'warehouse_id' => $balance->warehouse_id,
                    'lot_number' => $lotNumber,
                    'expiry_date' => $expiry,
                    'quantity' => $balance->quantity,
                    'received_at' => now(),
                ]);
                $movement = $this->recorder->handle(
                    $product, $balance->warehouse, 'lot_assignment', 0, $user,
                    'Asignación inicial de existencias al lote '.$lotNumber,
                    ['lot_managed' => true, 'assigned_quantity' => (float) $balance->quantity],
                );
                StockMovementLot::query()->create([
                    'stock_movement_id' => $movement->id,
                    'inventory_lot_id' => $lot->id,
                    'quantity' => $balance->quantity,
                ]);
            }
        });
    }
}
