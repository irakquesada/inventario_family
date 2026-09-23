<?php

namespace App\Services\Inventory;

use App\Events\InventoryChanged;
use App\Models\InventoryBalance;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use App\Notifications\LowStockNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RecordStockMovement
{
    public function handle(
        Product $product,
        Warehouse $warehouse,
        string $type,
        float $quantity,
        ?User $user = null,
        ?string $reason = null,
        array $metadata = [],
        ?string $operationReference = null,
    ): StockMovement {
        if ($product->tracks_lots && in_array($type, [
            'receipt', 'issue', 'adjustment', 'opening_balance', 'transfer_out',
            'transfer_in', 'stocktake_adjustment', 'purchase_receipt',
        ], true) && ! ($metadata['lot_managed'] ?? false)) {
            throw ValidationException::withMessages([
                'quantity' => 'Este producto requiere una operación gestionada por lotes.',
            ]);
        }

        if ($quantity === 0.0 && $type !== 'lot_assignment') {
            throw ValidationException::withMessages(['quantity' => 'La cantidad no puede ser cero.']);
        }

        [$movement, $previousQuantity] = DB::transaction(function () use ($product, $warehouse, $type, $quantity, $user, $reason, $metadata, $operationReference): array {
            InventoryBalance::query()->firstOrCreate(
                ['product_id' => $product->id, 'warehouse_id' => $warehouse->id],
                ['quantity' => 0],
            );

            $balance = InventoryBalance::query()
                ->whereBelongsTo($product)
                ->whereBelongsTo($warehouse)
                ->lockForUpdate()
                ->firstOrFail();

            $nextQuantity = round((float) $balance->quantity + $quantity, 3);
            $previousQuantity = (float) $balance->quantity;

            if ($nextQuantity < 0) {
                throw ValidationException::withMessages(['quantity' => 'El movimiento dejaría existencias negativas.']);
            }

            $balance->update(['quantity' => $nextQuantity]);

            $movement = StockMovement::query()->create([
                'reference' => (string) Str::uuid(),
                'operation_reference' => $operationReference,
                'product_id' => $product->id,
                'warehouse_id' => $warehouse->id,
                'user_id' => $user?->id,
                'type' => $type,
                'quantity' => $quantity,
                'balance_after' => $nextQuantity,
                'reason' => $reason,
                'metadata' => $metadata,
            ]);

            return [$movement, $previousQuantity];
        });

        $minimumStock = (float) $product->minimum_stock;

        if ($product->notify_low_stock && $previousQuantity > $minimumStock && (float) $movement->balance_after <= $minimumStock) {
            DB::afterCommit(function () use ($product, $warehouse, $movement): void {
                User::role('admin')->each(
                    fn (User $admin) => $admin->notify(new LowStockNotification($product, $warehouse, (float) $movement->balance_after))
                );
            });
        }

        DB::afterCommit(function () use ($movement): void {
            try {
                InventoryChanged::dispatch($movement);
            } catch (\Throwable $exception) {
                Log::warning('No se pudo emitir la actualización de inventario en tiempo real.', [
                    'movement_id' => $movement->id,
                    'exception' => $exception->getMessage(),
                ]);
            }
        });

        return $movement;
    }
}
