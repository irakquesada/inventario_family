<?php

namespace App\Services\Inventory;

use App\Models\InventoryBalance;
use App\Models\InventoryCount;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CompleteInventoryCount
{
    public function __construct(private RecordStockMovement $recorder, private LotStock $lotStock) {}

    public function handle(InventoryCount $inventoryCount, User $user): InventoryCount
    {
        return DB::transaction(function () use ($inventoryCount, $user): InventoryCount {
            $count = InventoryCount::query()->whereKey($inventoryCount->id)->lockForUpdate()->firstOrFail();

            if (! $count->isOpen()) {
                throw ValidationException::withMessages(['count' => 'El conteo ya no está abierto.']);
            }

            $items = $count->items()->with('product')->lockForUpdate()->get();
            $pending = $items->whereNull('counted_quantity')->count();

            if ($pending > 0) {
                throw ValidationException::withMessages([
                    'count' => "Faltan {$pending} productos por contar.",
                ]);
            }

            foreach ($items as $item) {
                InventoryBalance::query()->firstOrCreate(
                    ['product_id' => $item->product_id, 'warehouse_id' => $count->warehouse_id],
                    ['quantity' => 0],
                );
            }

            $lockedBalances = InventoryBalance::query()
                ->where('warehouse_id', $count->warehouse_id)
                ->whereIn('product_id', $items->pluck('product_id'))
                ->orderBy('product_id')
                ->lockForUpdate()
                ->get()
                ->keyBy('product_id');

            foreach ($items as $item) {
                $current = (float) $lockedBalances->get($item->product_id)->quantity;
                $difference = round((float) $item->counted_quantity - $current, 3);

                if ($difference === 0.0) {
                    continue;
                }

                $metadata = [
                    'inventory_count_id' => $count->id,
                    'expected_snapshot' => (float) $item->expected_quantity,
                    'counted_quantity' => (float) $item->counted_quantity,
                    'balance_before_reconciliation' => $current,
                ];
                $reason = 'Conciliación de conteo '.$count->reference;

                if ($item->product->tracks_lots && $difference > 0) {
                    if (! $item->reconciliation_lot_number) {
                        throw ValidationException::withMessages(['count' => "Indica el lote del sobrante de {$item->product->name}."]);
                    }
                    if ($item->product->requires_expiry && ! $item->reconciliation_expiry_date) {
                        throw ValidationException::withMessages(['count' => "Indica la caducidad del sobrante de {$item->product->name}."]);
                    }
                    $this->lotStock->receive(
                        $item->product, $count->warehouse, 'stocktake_adjustment', $difference, $user,
                        $reason, $item->reconciliation_lot_number,
                        $item->reconciliation_expiry_date, $metadata, $count->reference,
                    );
                } elseif ($item->product->tracks_lots) {
                    $this->lotStock->consume(
                        $item->product, $count->warehouse, 'stocktake_adjustment', abs($difference),
                        $user, $reason, true, $metadata, $count->reference,
                    );
                } else {
                    $this->recorder->handle(
                        $item->product, $count->warehouse, 'stocktake_adjustment', $difference,
                        $user, $reason, $metadata, $count->reference,
                    );
                }
            }

            $count->update([
                'status' => 'completed',
                'completed_by' => $user->id,
                'completed_at' => now(),
            ]);

            return $count->fresh();
        });
    }
}
