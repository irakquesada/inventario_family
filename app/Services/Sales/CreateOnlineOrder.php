<?php

namespace App\Services\Sales;

use App\Events\SaleChanged;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use App\Models\Warehouse;
use App\Notifications\NewOnlineOrderNotification;
use App\Notifications\OnlineOrderCustomerNotification;
use App\Services\Inventory\LotStock;
use App\Services\Inventory\RecordStockMovement;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CreateOnlineOrder
{
    public function __construct(private RecordStockMovement $recorder, private LotStock $lotStock) {}

    public function handle(Warehouse $warehouse, array $cart, array $customer, string $pickupDate, string $pickupTime, ?string $notes = null): Sale
    {
        if ($cart === []) {
            throw ValidationException::withMessages(['cart' => 'Tu carrito está vacío.']);
        }

        $sale = DB::transaction(function () use ($warehouse, $cart, $customer, $pickupDate, $pickupTime, $notes): Sale {
            $products = Product::query()->whereKey(array_keys($cart))->lockForUpdate()->get()->keyBy('id');
            $lines = [];
            $subtotal = 0.0;
            foreach ($cart as $productId => $quantity) {
                $product = $products->get((int) $productId);
                $quantity = round((float) $quantity, 3);
                if (! $product || ! $product->is_active || ! $product->is_sellable || ! $product->is_online_sellable || $product->price === null || $quantity <= 0) {
                    throw ValidationException::withMessages(['cart' => 'Uno de los productos ya no está disponible para compra online.']);
                }
                $available = (float) $product->balances()->where('warehouse_id', $warehouse->id)->lockForUpdate()->sum('quantity');
                if ($available < $quantity) {
                    throw ValidationException::withMessages(['cart' => "No queda cantidad suficiente de {$product->name}."]);
                }
                $lineTotal = round((float) $product->price * $quantity, 2);
                $subtotal += $lineTotal;
                $lines[] = [$product, $quantity, $lineTotal];
            }

            $sale = Sale::query()->create([
                'reference' => (string) Str::uuid(), 'warehouse_id' => $warehouse->id, 'user_id' => null,
                'status' => 'confirmed', 'channel' => 'online', 'fulfilment_method' => 'pickup',
                'pickup_date' => $pickupDate, 'pickup_time' => $pickupTime, 'payment_method' => 'pickup',
                'customer_name' => $customer['name'], 'customer_email' => $customer['email'],
                'customer_phone' => $customer['phone'], 'subtotal' => round($subtotal, 2), 'discount' => 0,
                'total' => round($subtotal, 2), 'notes' => $notes ?: null, 'confirmed_at' => now(),
            ]);
            $sale->update(['number' => 'WEB-'.now()->format('Ymd').'-'.str_pad((string) $sale->id, 6, '0', STR_PAD_LEFT)]);

            foreach ($lines as [$product, $quantity, $lineTotal]) {
                $metadata = ['sale_id' => $sale->id, 'sale_number' => $sale->number, 'channel' => 'online', 'fulfilment' => 'pickup'];
                $movement = $product->tracks_lots
                    ? $this->lotStock->consume($product, $warehouse, 'sale', $quantity, null, "Pedido online {$sale->number}", false, $metadata, $sale->reference)
                    : $this->recorder->handle($product, $warehouse, 'sale', -$quantity, null, "Pedido online {$sale->number}", $metadata, $sale->reference);
                $sale->items()->create([
                    'product_id' => $product->id, 'stock_movement_id' => $movement->id, 'product_name' => $product->name,
                    'product_sku' => $product->sku, 'unit' => $product->unit, 'quantity' => $quantity,
                    'unit_price' => $product->price, 'unit_cost' => $product->cost, 'line_total' => $lineTotal,
                ]);
            }
            $sale->statusHistory()->create(['to_status' => 'confirmed', 'notes' => 'Pedido confirmado desde la tienda online.']);

            return $sale;
        });

        DB::afterCommit(function () use ($sale): void {
            try {
                User::role('admin')->each(fn (User $admin) => $admin->notify(new NewOnlineOrderNotification($sale)));
                Notification::route('mail', $sale->customer_email)->notify(new OnlineOrderCustomerNotification($sale));
                SaleChanged::dispatch($sale);
            } catch (\Throwable $exception) {
                Log::warning('El pedido se guardó, pero alguna notificación no pudo enviarse.', ['sale_id' => $sale->id, 'exception' => $exception->getMessage()]);
            }
        });

        return $sale->load(['items', 'warehouse']);
    }
}
