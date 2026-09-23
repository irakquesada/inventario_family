<?php

namespace App\Services\Inventory;

use App\Models\InventoryBalance;
use App\Models\InventoryLot;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\StockMovementLot;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TransferStock
{
    public function __construct(private RecordStockMovement $recorder) {}

    /** @return array{out: StockMovement, in: StockMovement} */
    public function handle(
        Product $product,
        Warehouse $from,
        Warehouse $to,
        float $quantity,
        User $user,
        string $reason,
    ): array {
        if ($from->is($to)) {
            throw ValidationException::withMessages(['toWarehouseId' => 'El almacén de destino debe ser diferente al de origen.']);
        }

        if ($quantity <= 0) {
            throw ValidationException::withMessages(['quantity' => 'La cantidad debe ser mayor que cero.']);
        }

        return DB::transaction(function () use ($product, $from, $to, $quantity, $user, $reason): array {
            foreach ([$from, $to] as $warehouse) {
                InventoryBalance::query()->firstOrCreate(
                    ['product_id' => $product->id, 'warehouse_id' => $warehouse->id],
                    ['quantity' => 0],
                );
            }

            InventoryBalance::query()
                ->where('product_id', $product->id)
                ->whereIn('warehouse_id', [$from->id, $to->id])
                ->orderBy('warehouse_id')
                ->lockForUpdate()
                ->get();

            $operationReference = (string) Str::uuid();

            if ($product->tracks_lots) {
                return $this->transferLots($product, $from, $to, $quantity, $user, $reason, $operationReference);
            }

            $out = $this->recorder->handle(
                $product, $from, 'transfer_out', -$quantity, $user, $reason,
                ['destination_warehouse_id' => $to->id], $operationReference,
            );
            $in = $this->recorder->handle(
                $product, $to, 'transfer_in', $quantity, $user, $reason,
                ['source_warehouse_id' => $from->id], $operationReference,
            );

            return compact('out', 'in');
        });
    }

    /** @return array{out: StockMovement, in: StockMovement} */
    private function transferLots(Product $product, Warehouse $from, Warehouse $to, float $quantity, User $user, string $reason, string $operationReference): array
    {
        $sourceLots = InventoryLot::query()
            ->whereBelongsTo($product)
            ->whereBelongsTo($from)
            ->where('quantity', '>', 0)
            ->orderByRaw('expiry_date IS NULL')
            ->orderBy('expiry_date')
            ->orderBy('received_at')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        if (round((float) $sourceLots->sum('quantity'), 3) < round($quantity, 3)) {
            throw ValidationException::withMessages(['quantity' => 'No hay cantidad suficiente distribuida entre los lotes de origen.']);
        }

        $out = $this->recorder->handle(
            $product, $from, 'transfer_out', -$quantity, $user, $reason,
            ['destination_warehouse_id' => $to->id, 'lot_managed' => true, 'allocation_method' => 'FEFO'],
            $operationReference,
        );
        $in = $this->recorder->handle(
            $product, $to, 'transfer_in', $quantity, $user, $reason,
            ['source_warehouse_id' => $from->id, 'lot_managed' => true, 'allocation_method' => 'FEFO'],
            $operationReference,
        );
        $remaining = $quantity;

        foreach ($sourceLots as $sourceLot) {
            if ($remaining <= 0) {
                break;
            }
            $moved = min((float) $sourceLot->quantity, $remaining);
            $destinationLot = InventoryLot::query()->firstOrCreate(
                ['product_id' => $product->id, 'warehouse_id' => $to->id, 'lot_number' => $sourceLot->lot_number],
                ['expiry_date' => $sourceLot->expiry_date, 'quantity' => 0, 'received_at' => $sourceLot->received_at],
            );
            $destinationLot = InventoryLot::query()->whereKey($destinationLot->id)->lockForUpdate()->firstOrFail();

            if ($destinationLot->expiry_date?->toDateString() !== $sourceLot->expiry_date?->toDateString()) {
                throw ValidationException::withMessages(['quantity' => "El lote {$sourceLot->lot_number} tiene otra caducidad en el destino."]);
            }

            $sourceLot->update(['quantity' => round((float) $sourceLot->quantity - $moved, 3)]);
            $destinationLot->update(['quantity' => round((float) $destinationLot->quantity + $moved, 3)]);
            StockMovementLot::query()->insert([
                ['stock_movement_id' => $out->id, 'inventory_lot_id' => $sourceLot->id, 'quantity' => -$moved],
                ['stock_movement_id' => $in->id, 'inventory_lot_id' => $destinationLot->id, 'quantity' => $moved],
            ]);
            $remaining = round($remaining - $moved, 3);
        }

        return compact('out', 'in');
    }
}
