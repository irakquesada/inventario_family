<?php

namespace Tests\Feature\Inventory;

use App\Models\InventoryCountItem;
use App\Models\InventoryLot;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Inventory\CompleteInventoryCount;
use App\Services\Inventory\CreateInventoryCount;
use App\Services\Inventory\InitializeLotTracking;
use App\Services\Inventory\LotStock;
use App\Services\Inventory\RecordStockMovement;
use App\Services\Inventory\TransferStock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class LotStockTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Warehouse $warehouse;

    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->warehouse = Warehouse::query()->create(['name' => 'Principal', 'code' => 'MAIN']);
        $this->product = Product::query()->create([
            'name' => 'Queso fresco', 'slug' => 'queso-fresco', 'sku' => 'QUE-001',
            'tracks_lots' => true, 'requires_expiry' => true, 'expiry_warning_days' => 5,
        ]);
    }

    public function test_receipt_updates_general_balance_lot_and_allocation_atomically(): void
    {
        $movement = app(LotStock::class)->receive(
            $this->product, $this->warehouse, 'receipt', 10, $this->user,
            'Compra', 'lote-a', today()->addDays(10),
        );

        $lot = InventoryLot::query()->firstOrFail();
        $this->assertSame('LOTE-A', $lot->lot_number);
        $this->assertSame(10.0, (float) $lot->quantity);
        $this->assertDatabaseHas('inventory_balances', [
            'product_id' => $this->product->id, 'warehouse_id' => $this->warehouse->id, 'quantity' => 10,
        ]);
        $this->assertDatabaseHas('stock_movement_lots', [
            'stock_movement_id' => $movement->id, 'inventory_lot_id' => $lot->id, 'quantity' => 10,
        ]);
    }

    public function test_expiry_is_required_and_direct_unallocated_movements_are_rejected(): void
    {
        try {
            app(LotStock::class)->receive($this->product, $this->warehouse, 'receipt', 2, $this->user, 'Compra', 'L1');
            $this->fail('Debía exigir caducidad.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('expiryDate', $exception->errors());
        }

        $this->expectException(ValidationException::class);
        app(RecordStockMovement::class)->handle($this->product, $this->warehouse, 'receipt', 2, $this->user);
    }

    public function test_issue_uses_fefo_and_never_consumes_an_expired_lot(): void
    {
        $lotStock = app(LotStock::class);
        $lotStock->receive($this->product, $this->warehouse, 'receipt', 3, $this->user, 'Compra', 'CADUCADO', today()->subDay());
        $lotStock->receive($this->product, $this->warehouse, 'receipt', 4, $this->user, 'Compra', 'PRIMERO', today()->addDays(2));
        $lotStock->receive($this->product, $this->warehouse, 'receipt', 5, $this->user, 'Compra', 'DESPUES', today()->addDays(8));

        $movement = $lotStock->consume($this->product, $this->warehouse, 'issue', 6, $this->user, 'Consumo');

        $this->assertSame(3.0, (float) InventoryLot::query()->where('lot_number', 'CADUCADO')->value('quantity'));
        $this->assertSame(0.0, (float) InventoryLot::query()->where('lot_number', 'PRIMERO')->value('quantity'));
        $this->assertSame(3.0, (float) InventoryLot::query()->where('lot_number', 'DESPUES')->value('quantity'));
        $this->assertSame(2, $movement->lotAllocations()->count());
        $this->assertSame(6.0, abs((float) $movement->lotAllocations()->sum('quantity')));
    }

    public function test_insufficient_non_expired_quantity_rolls_back_without_touching_stock(): void
    {
        $lotStock = app(LotStock::class);
        $lotStock->receive($this->product, $this->warehouse, 'receipt', 5, $this->user, 'Compra', 'VENCIDO', today()->subDay());

        try {
            $lotStock->consume($this->product, $this->warehouse, 'issue', 1, $this->user, 'Consumo');
            $this->fail('La salida debía rechazarse.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('caducado', $exception->errors()['quantity'][0]);
        }

        $this->assertSame(5.0, (float) InventoryLot::query()->value('quantity'));
        $this->assertDatabaseHas('inventory_balances', ['quantity' => 5]);
        $this->assertDatabaseCount('stock_movements', 1);
    }

    public function test_transfer_preserves_each_lot_number_expiry_and_total_integrity(): void
    {
        $destination = Warehouse::query()->create(['name' => 'Cocina', 'code' => 'KIT']);
        $lotStock = app(LotStock::class);
        $lotStock->receive($this->product, $this->warehouse, 'receipt', 2, $this->user, 'Compra', 'A', today()->addDays(2));
        $lotStock->receive($this->product, $this->warehouse, 'receipt', 5, $this->user, 'Compra', 'B', today()->addDays(5));

        $result = app(TransferStock::class)->handle($this->product, $this->warehouse, $destination, 4, $this->user, 'Reposición');

        $this->assertDatabaseHas('inventory_lots', ['warehouse_id' => $destination->id, 'lot_number' => 'A', 'quantity' => 2]);
        $this->assertDatabaseHas('inventory_lots', ['warehouse_id' => $destination->id, 'lot_number' => 'B', 'quantity' => 2]);
        $this->assertSame(2, $result['out']->lotAllocations()->count());
        $this->assertSame(2, $result['in']->lotAllocations()->count());
        $this->assertSame(3.0, (float) InventoryLot::query()->where('warehouse_id', $this->warehouse->id)->sum('quantity'));
        $this->assertSame(4.0, (float) InventoryLot::query()->where('warehouse_id', $destination->id)->sum('quantity'));
    }

    public function test_positive_stocktake_difference_requires_and_creates_reconciliation_lot(): void
    {
        app(LotStock::class)->receive($this->product, $this->warehouse, 'receipt', 5, $this->user, 'Compra', 'ORIGINAL', today()->addDays(5));
        $count = app(CreateInventoryCount::class)->handle($this->warehouse, $this->user);
        $item = InventoryCountItem::query()->where('inventory_count_id', $count->id)->where('product_id', $this->product->id)->firstOrFail();
        $item->update([
            'counted_quantity' => 7, 'counted_by' => $this->user->id, 'counted_at' => now(),
            'reconciliation_lot_number' => 'SOBRANTE',
            'reconciliation_expiry_date' => today()->addDays(6),
        ]);

        app(CompleteInventoryCount::class)->handle($count, $this->user);

        $this->assertDatabaseHas('inventory_lots', [
            'product_id' => $this->product->id, 'lot_number' => 'SOBRANTE', 'quantity' => 2,
        ]);
        $this->assertDatabaseHas('inventory_balances', ['product_id' => $this->product->id, 'quantity' => 7]);
    }

    public function test_existing_stock_can_be_assigned_to_an_initial_lot_without_changing_balance(): void
    {
        $product = Product::query()->create([
            'name' => 'Harina', 'slug' => 'harina', 'sku' => 'HAR-001',
            'tracks_lots' => false, 'requires_expiry' => false,
        ]);
        app(RecordStockMovement::class)->handle(
            $product, $this->warehouse, 'opening_balance', 8, $this->user, 'Saldo inicial',
        );
        $product->update(['tracks_lots' => true, 'requires_expiry' => true]);

        app(InitializeLotTracking::class)->handle($product->fresh(), $this->user, 'INICIAL', today()->addMonth()->toDateString());

        $this->assertDatabaseHas('inventory_balances', [
            'product_id' => $product->id, 'warehouse_id' => $this->warehouse->id, 'quantity' => 8,
        ]);
        $this->assertDatabaseHas('inventory_lots', [
            'product_id' => $product->id, 'warehouse_id' => $this->warehouse->id,
            'lot_number' => 'INICIAL', 'quantity' => 8,
        ]);
        $movement = $product->movements()->where('type', 'lot_assignment')->firstOrFail();
        $this->assertSame(0.0, (float) $movement->quantity);
        $this->assertSame(8.0, (float) $movement->lotAllocations()->sum('quantity'));
    }
}
