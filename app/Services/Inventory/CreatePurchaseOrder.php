<?php

namespace App\Services\Inventory;

use App\Models\ProductSupplier;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CreatePurchaseOrder
{
    public function handle(Supplier $supplier, Warehouse $warehouse, User $user, array $lines, ?string $expectedAt = null, ?string $notes = null): PurchaseOrder
    {
        if ($lines === []) {
            throw ValidationException::withMessages(['lines' => 'Añade al menos un producto a la orden.']);
        }

        return DB::transaction(function () use ($supplier, $warehouse, $user, $lines, $expectedAt, $notes): PurchaseOrder {
            $order = PurchaseOrder::query()->create([
                'number' => 'OC-'.now()->format('Ymd').'-'.Str::upper(Str::random(6)),
                'supplier_id' => $supplier->id,
                'warehouse_id' => $warehouse->id,
                'created_by' => $user->id,
                'expected_at' => $expectedAt ?: null,
                'notes' => $notes ?: null,
            ]);

            foreach ($lines as $line) {
                $reference = ProductSupplier::query()
                    ->where('supplier_id', $supplier->id)
                    ->where('product_id', $line['product_id'])
                    ->first();
                if (! $reference) {
                    throw ValidationException::withMessages(['lines' => 'Todos los productos deben estar vinculados al proveedor.']);
                }
                $quantity = round((float) $line['quantity'], 3);
                if ($quantity <= 0) {
                    throw ValidationException::withMessages(['lines' => 'Todas las cantidades deben ser mayores que cero.']);
                }
                $order->lines()->create([
                    'product_id' => $line['product_id'],
                    'supplier_sku' => $reference->supplier_sku,
                    'ordered_quantity' => $quantity,
                    'unit_cost' => $line['unit_cost'] !== '' && $line['unit_cost'] !== null ? $line['unit_cost'] : $reference->unit_cost,
                ]);
            }

            return $order->load('lines.product');
        });
    }
}
