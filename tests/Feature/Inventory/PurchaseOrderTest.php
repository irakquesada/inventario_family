<?php

namespace Tests\Feature\Inventory;

use App\Models\Product;
use App\Models\ProductSupplier;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Inventory\CreatePurchaseOrder;
use App\Services\Inventory\ReceivePurchaseOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PurchaseOrderTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Supplier $supplier;

    protected Warehouse $warehouse;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->supplier = Supplier::query()->create(['name' => 'Distribuidor']);
        $this->warehouse = Warehouse::query()->create(['name' => 'Principal', 'code' => 'MAIN']);
    }

    public function test_partial_and_final_receipts_update_stock_and_order_status(): void
    {
        $product = Product::query()->create(['name' => 'Arroz', 'slug' => 'arroz', 'sku' => 'ARR-1']);
        ProductSupplier::query()->create(['supplier_id' => $this->supplier->id, 'product_id' => $product->id, 'unit_cost' => 1.25, 'minimum_order_quantity' => 1]);
        $order = app(CreatePurchaseOrder::class)->handle($this->supplier, $this->warehouse, $this->user, [['product_id' => $product->id, 'quantity' => 10, 'unit_cost' => 1.30]]);
        $order->update(['status' => 'ordered', 'ordered_at' => now(), 'ordered_by' => $this->user->id]);

        $first = app(ReceivePurchaseOrder::class)->handle($order, $this->user, [$order->lines->first()->id => ['quantity' => 4]]);
        $this->assertSame('partially_received', $order->fresh()->status);
        $this->assertDatabaseHas('inventory_balances', ['product_id' => $product->id, 'quantity' => 4]);
        $this->assertDatabaseHas('purchase_receipt_lines', ['purchase_receipt_id' => $first->id, 'quantity' => 4]);

        app(ReceivePurchaseOrder::class)->handle($order->fresh(), $this->user, [$order->lines->first()->id => ['quantity' => 6]]);
        $this->assertSame('received', $order->fresh()->status);
        $this->assertDatabaseHas('inventory_balances', ['product_id' => $product->id, 'quantity' => 10]);
        $this->assertDatabaseCount('purchase_receipts', 2);
    }

    public function test_lot_controlled_receipt_creates_lot_and_movement_trace(): void
    {
        $product = Product::query()->create(['name' => 'Yogur', 'slug' => 'yogur', 'sku' => 'YOG-1', 'tracks_lots' => true, 'requires_expiry' => true]);
        ProductSupplier::query()->create(['supplier_id' => $this->supplier->id, 'product_id' => $product->id, 'minimum_order_quantity' => 1]);
        $order = app(CreatePurchaseOrder::class)->handle($this->supplier, $this->warehouse, $this->user, [['product_id' => $product->id, 'quantity' => 5, 'unit_cost' => null]]);
        $order->update(['status' => 'ordered']);

        $receipt = app(ReceivePurchaseOrder::class)->handle($order, $this->user, [$order->lines->first()->id => ['quantity' => 5, 'lot_number' => 'FAB-22', 'expiry_date' => today()->addMonth()->toDateString()]]);

        $this->assertDatabaseHas('inventory_lots', ['product_id' => $product->id, 'lot_number' => 'FAB-22', 'quantity' => 5]);
        $this->assertSame('purchase_receipt', $receipt->lines->first()->movement->type);
        $this->assertSame(1, $receipt->lines->first()->movement->lotAllocations()->count());
    }

    public function test_over_receipt_is_rejected_without_partial_writes(): void
    {
        $product = Product::query()->create(['name' => 'Pan', 'slug' => 'pan', 'sku' => 'PAN-1']);
        ProductSupplier::query()->create(['supplier_id' => $this->supplier->id, 'product_id' => $product->id, 'minimum_order_quantity' => 1]);
        $order = app(CreatePurchaseOrder::class)->handle($this->supplier, $this->warehouse, $this->user, [['product_id' => $product->id, 'quantity' => 3, 'unit_cost' => null]]);
        $order->update(['status' => 'ordered']);

        try {
            app(ReceivePurchaseOrder::class)->handle($order, $this->user, [$order->lines->first()->id => ['quantity' => 4]]);
            $this->fail('Debía impedir recibir más de lo pedido.');
        } catch (ValidationException $exception) {
            $this->assertNotEmpty($exception->errors());
        }
        $this->assertDatabaseCount('purchase_receipts', 0);
        $this->assertDatabaseCount('stock_movements', 0);
    }
}
