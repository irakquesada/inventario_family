<?php

namespace App\Services\Inventory;

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\PurchaseReceipt;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ReceivePurchaseOrder
{
    public function __construct(private RecordStockMovement $recorder, private LotStock $lotStock) {}

    public function handle(PurchaseOrder $order, User $user, array $lines, ?string $notes = null): PurchaseReceipt
    {
        return DB::transaction(function () use ($order, $user, $lines, $notes): PurchaseReceipt {
            $order = PurchaseOrder::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            if (! in_array($order->status, ['ordered', 'partially_received'], true)) {
                throw ValidationException::withMessages(['order' => 'La orden debe estar enviada y pendiente de recepción.']);
            }

            $prepared = [];
            foreach ($lines as $lineId => $data) {
                $quantity = round((float) ($data['quantity'] ?? 0), 3);
                if ($quantity <= 0) {
                    continue;
                }
                $line = PurchaseOrderLine::query()->where('purchase_order_id', $order->id)->whereKey($lineId)->lockForUpdate()->firstOrFail();
                $pending = round((float) $line->ordered_quantity - (float) $line->received_quantity, 3);
                if ($quantity > $pending) {
                    throw ValidationException::withMessages(["receiptLines.{$lineId}.quantity" => "Solo quedan {$pending} unidades pendientes."]);
                }
                $line->load('product');
                if ($line->product->tracks_lots && trim((string) ($data['lot_number'] ?? '')) === '') {
                    throw ValidationException::withMessages(["receiptLines.{$lineId}.lot_number" => 'Indica el número de lote.']);
                }
                if ($line->product->requires_expiry && empty($data['expiry_date'])) {
                    throw ValidationException::withMessages(["receiptLines.{$lineId}.expiry_date" => 'Indica la fecha de caducidad.']);
                }
                $prepared[] = [$line, $quantity, $data];
            }
            if ($prepared === []) {
                throw ValidationException::withMessages(['receiptLines' => 'Indica al menos una cantidad recibida.']);
            }

            $receipt = PurchaseReceipt::query()->create([
                'number' => 'RC-'.now()->format('Ymd').'-'.Str::upper(Str::random(6)),
                'purchase_order_id' => $order->id,
                'warehouse_id' => $order->warehouse_id,
                'received_by' => $user->id,
                'received_at' => now(),
                'notes' => $notes ?: null,
            ]);

            foreach ($prepared as [$line, $quantity, $data]) {
                $reason = "Recepción {$receipt->number} de {$order->number}";
                $metadata = ['purchase_order_id' => $order->id, 'purchase_receipt_id' => $receipt->id, 'unit_cost' => $line->unit_cost];
                $movement = $line->product->tracks_lots
                    ? $this->lotStock->receive($line->product, $order->warehouse, 'purchase_receipt', $quantity, $user, $reason, $data['lot_number'], ($data['expiry_date'] ?? null) ?: null, $metadata, $receipt->number)
                    : $this->recorder->handle($line->product, $order->warehouse, 'purchase_receipt', $quantity, $user, $reason, $metadata, $receipt->number);

                $line->increment('received_quantity', $quantity);
                $receipt->lines()->create([
                    'purchase_order_line_id' => $line->id,
                    'stock_movement_id' => $movement->id,
                    'quantity' => $quantity,
                    'unit_cost' => $line->unit_cost,
                    'lot_number' => $line->product->tracks_lots ? Str::upper(trim($data['lot_number'])) : null,
                    'expiry_date' => ($data['expiry_date'] ?? null) ?: null,
                ]);
            }

            $hasPending = $order->lines()->whereColumn('received_quantity', '<', 'ordered_quantity')->exists();
            $order->update([
                'status' => $hasPending ? 'partially_received' : 'received',
                'completed_at' => $hasPending ? null : now(),
            ]);

            return $receipt->load(['lines.orderLine.product', 'receiver']);
        });
    }
}
