<?php

namespace App\Services\Inventory;

use App\Models\InventoryBalance;
use App\Models\InventoryLot;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\StockMovementLot;
use App\Models\User;
use App\Models\Warehouse;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LotStock
{
    public function __construct(private RecordStockMovement $recorder) {}

    public function receive(
        Product $product,
        Warehouse $warehouse,
        string $type,
        float $quantity,
        User $user,
        string $reason,
        string $lotNumber,
        CarbonInterface|string|null $expiryDate = null,
        array $metadata = [],
        ?string $operationReference = null,
    ): StockMovement {
        $this->ensureTracked($product);
        $lotNumber = Str::upper(trim($lotNumber));
        $expiry = $expiryDate ? now()->parse($expiryDate)->toDateString() : null;

        if ($quantity <= 0 || $lotNumber === '') {
            throw ValidationException::withMessages(['lotNumber' => 'El lote y una cantidad positiva son obligatorios.']);
        }
        if ($product->requires_expiry && $expiry === null) {
            throw ValidationException::withMessages(['expiryDate' => 'Este producto requiere fecha de caducidad.']);
        }

        return DB::transaction(function () use ($product, $warehouse, $type, $quantity, $user, $reason, $lotNumber, $expiry, $metadata, $operationReference): StockMovement {
            $this->lockBalance($product, $warehouse);
            $lot = InventoryLot::query()->firstOrCreate(
                ['product_id' => $product->id, 'warehouse_id' => $warehouse->id, 'lot_number' => $lotNumber],
                ['expiry_date' => $expiry, 'quantity' => 0, 'received_at' => now()],
            );
            $lot = InventoryLot::query()->whereKey($lot->id)->lockForUpdate()->firstOrFail();

            if ($lot->expiry_date?->toDateString() !== $expiry) {
                throw ValidationException::withMessages(['expiryDate' => 'Ese número de lote ya existe con otra fecha de caducidad.']);
            }

            $wasEmpty = (float) $lot->quantity <= 0;
            $movement = $this->recorder->handle(
                $product, $warehouse, $type, $quantity, $user, $reason,
                [...$metadata, 'lot_managed' => true], $operationReference,
            );
            $lot->update([
                'quantity' => round((float) $lot->quantity + $quantity, 3),
                'expiry_notified_at' => $wasEmpty ? null : $lot->expiry_notified_at,
            ]);
            StockMovementLot::query()->create([
                'stock_movement_id' => $movement->id,
                'inventory_lot_id' => $lot->id,
                'quantity' => $quantity,
            ]);

            return $movement;
        });
    }

    public function consume(
        Product $product,
        Warehouse $warehouse,
        string $type,
        float $quantity,
        User $user,
        string $reason,
        bool $allowExpired = false,
        array $metadata = [],
        ?string $operationReference = null,
    ): StockMovement {
        $this->ensureTracked($product);

        if ($quantity <= 0) {
            throw ValidationException::withMessages(['quantity' => 'La cantidad debe ser mayor que cero.']);
        }

        return DB::transaction(function () use ($product, $warehouse, $type, $quantity, $user, $reason, $allowExpired, $metadata, $operationReference): StockMovement {
            $this->lockBalance($product, $warehouse);
            $lots = InventoryLot::query()
                ->whereBelongsTo($product)
                ->whereBelongsTo($warehouse)
                ->where('quantity', '>', 0)
                ->when(! $allowExpired, fn ($query) => $query->where(fn ($query) => $query->whereNull('expiry_date')->orWhereDate('expiry_date', '>=', today())))
                ->orderByRaw('expiry_date IS NULL')
                ->orderBy('expiry_date')
                ->orderBy('received_at')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if (round((float) $lots->sum('quantity'), 3) < round($quantity, 3)) {
                throw ValidationException::withMessages([
                    'quantity' => $allowExpired
                        ? 'No hay cantidad suficiente distribuida entre los lotes.'
                        : 'No hay cantidad suficiente en lotes utilizables; puede haber stock caducado.',
                ]);
            }

            $movement = $this->recorder->handle(
                $product, $warehouse, $type, -$quantity, $user, $reason,
                [...$metadata, 'lot_managed' => true, 'allocation_method' => 'FEFO'], $operationReference,
            );
            $remaining = $quantity;

            foreach ($lots as $lot) {
                if ($remaining <= 0) {
                    break;
                }
                $used = min((float) $lot->quantity, $remaining);
                $lot->update(['quantity' => round((float) $lot->quantity - $used, 3)]);
                StockMovementLot::query()->create([
                    'stock_movement_id' => $movement->id,
                    'inventory_lot_id' => $lot->id,
                    'quantity' => -$used,
                ]);
                $remaining = round($remaining - $used, 3);
            }

            return $movement;
        });
    }

    private function lockBalance(Product $product, Warehouse $warehouse): void
    {
        InventoryBalance::query()->firstOrCreate(
            ['product_id' => $product->id, 'warehouse_id' => $warehouse->id],
            ['quantity' => 0],
        );
        InventoryBalance::query()
            ->whereBelongsTo($product)
            ->whereBelongsTo($warehouse)
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function ensureTracked(Product $product): void
    {
        if (! $product->tracks_lots) {
            throw ValidationException::withMessages(['product' => 'El producto no tiene activado el control por lotes.']);
        }
    }
}
