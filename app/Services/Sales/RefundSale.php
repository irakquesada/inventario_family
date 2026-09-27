<?php

namespace App\Services\Sales;

use App\Events\SaleChanged;
use App\Models\Sale;
use App\Models\User;
use App\Services\Inventory\LotStock;
use App\Services\Inventory\RecordStockMovement;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class RefundSale
{
    public function __construct(private RecordStockMovement $recorder, private LotStock $lotStock) {}

    public function handle(Sale $sale, User $user, string $reason): Sale
    {
        if (mb_strlen(trim($reason)) < 3) {
            throw ValidationException::withMessages(['refundReason' => 'Explica brevemente el motivo.']);
        }

        return DB::transaction(function () use ($sale, $user, $reason): Sale {
            $sale = Sale::query()->whereKey($sale)->lockForUpdate()->firstOrFail();
            if (! in_array($sale->status, ['confirmed', 'preparing', 'ready', 'completed'], true)) {
                throw ValidationException::withMessages(['refundReason' => 'Esta venta ya no admite devolución.']);
            }
            $previous = $sale->status;
            $sale->load(['items.product', 'items.stockMovement.lotAllocations.lot', 'warehouse']);
            foreach ($sale->items as $item) {
                $metadata = ['sale_id' => $sale->id, 'sale_number' => $sale->number, 'reverses_movement_id' => $item->stock_movement_id];
                if ($item->product->tracks_lots) {
                    $this->lotStock->restore($item->stockMovement, $user, "Devolución {$sale->number}: {$reason}", $metadata, $sale->reference);
                } else {
                    $this->recorder->handle($item->product, $sale->warehouse, 'sale_return', (float) $item->quantity, $user, "Devolución {$sale->number}: {$reason}", $metadata, $sale->reference);
                }
            }
            $sale->update(['status' => 'refunded', 'cancelled_at' => now(), 'cancelled_by' => $user->id, 'cancellation_reason' => trim($reason)]);
            $sale->statusHistory()->create(['user_id' => $user->id, 'from_status' => $previous, 'to_status' => 'refunded', 'notes' => trim($reason)]);
            DB::afterCommit(function () use ($sale): void {
                try {
                    SaleChanged::dispatch($sale);
                } catch (\Throwable $exception) {
                    Log::warning('No se pudo emitir la actualización de ventas en tiempo real.', ['sale_id' => $sale->id, 'exception' => $exception->getMessage()]);
                }
            });

            return $sale->fresh();
        });
    }
}
