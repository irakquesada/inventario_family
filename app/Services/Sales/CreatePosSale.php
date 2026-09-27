<?php

namespace App\Services\Sales;

use App\Events\SaleChanged;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Inventory\LotStock;
use App\Services\Inventory\RecordStockMovement;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CreatePosSale
{
    public function __construct(private RecordStockMovement $recorder, private LotStock $lotStock) {}

    public function handle(Warehouse $warehouse, User $user, array $cart, string $paymentMethod, float $discount = 0, array $customer = [], ?string $notes = null): Sale
    {
        if ($cart === []) {
            throw ValidationException::withMessages(['cart' => 'Añade al menos un producto.']);
        }
        if (! in_array($paymentMethod, ['cash', 'card', 'transfer', 'other'], true)) {
            throw ValidationException::withMessages(['paymentMethod' => 'Selecciona una forma de pago válida.']);
        }

        $sale = DB::transaction(function () use ($warehouse, $user, $cart, $paymentMethod, $discount, $customer, $notes): Sale {
            $products = Product::query()->whereKey(array_keys($cart))->lockForUpdate()->get()->keyBy('id');
            $lines = [];
            $subtotal = 0.0;
            foreach ($cart as $productId => $quantity) {
                $product = $products->get((int) $productId);
                $quantity = round((float) $quantity, 3);
                if (! $product || ! $product->is_active || ! $product->is_sellable || $product->price === null || $quantity <= 0) {
                    throw ValidationException::withMessages(['cart' => 'El carrito contiene un producto no disponible para venta.']);
                }
                $lineTotal = round((float) $product->price * $quantity, 2);
                $subtotal += $lineTotal;
                $lines[] = [$product, $quantity, $lineTotal];
            }
            $subtotal = round($subtotal, 2);
            $discount = round($discount, 2);
            if ($discount < 0 || $discount > $subtotal) {
                throw ValidationException::withMessages(['discount' => 'El descuento no puede superar el subtotal.']);
            }

            $sale = Sale::query()->create([
                'reference' => (string) Str::uuid(), 'warehouse_id' => $warehouse->id, 'user_id' => $user->id,
                'status' => 'completed', 'payment_method' => $paymentMethod,
                'customer_name' => ($customer['name'] ?? '') ?: null, 'customer_email' => ($customer['email'] ?? '') ?: null,
                'customer_phone' => ($customer['phone'] ?? '') ?: null, 'subtotal' => $subtotal,
                'discount' => $discount, 'total' => round($subtotal - $discount, 2), 'notes' => $notes ?: null,
                'confirmed_at' => now(), 'completed_at' => now(),
            ]);
            $sale->update(['number' => 'V-'.now()->format('Ymd').'-'.str_pad((string) $sale->id, 6, '0', STR_PAD_LEFT)]);

            foreach ($lines as [$product, $quantity, $lineTotal]) {
                $metadata = ['sale_id' => $sale->id, 'sale_number' => $sale->number, 'channel' => 'pos'];
                $movement = $product->tracks_lots
                    ? $this->lotStock->consume($product, $warehouse, 'sale', $quantity, $user, "Venta {$sale->number}", false, $metadata, $sale->reference)
                    : $this->recorder->handle($product, $warehouse, 'sale', -$quantity, $user, "Venta {$sale->number}", $metadata, $sale->reference);
                $sale->items()->create([
                    'product_id' => $product->id, 'stock_movement_id' => $movement->id, 'product_name' => $product->name,
                    'product_sku' => $product->sku, 'unit' => $product->unit, 'quantity' => $quantity,
                    'unit_price' => $product->price, 'unit_cost' => $product->cost, 'line_total' => $lineTotal,
                ]);
            }
            $sale->statusHistory()->create(['user_id' => $user->id, 'to_status' => 'completed', 'notes' => 'Venta completada desde TPV.']);
            DB::afterCommit(function () use ($sale): void {
                try {
                    SaleChanged::dispatch($sale);
                } catch (\Throwable $exception) {
                    Log::warning('No se pudo emitir la actualización de ventas en tiempo real.', ['sale_id' => $sale->id, 'exception' => $exception->getMessage()]);
                }
            });

            return $sale;
        });

        return $sale->load(['items', 'warehouse', 'user']);
    }
}
