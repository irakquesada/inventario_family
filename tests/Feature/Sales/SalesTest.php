<?php

namespace Tests\Feature\Sales;

use App\Livewire\Sales\PosTerminal;
use App\Models\InventoryLot;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Inventory\LotStock;
use App\Services\Inventory\RecordStockMovement;
use App\Services\Sales\CreatePosSale;
use App\Services\Sales\RefundSale;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class SalesTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Warehouse $warehouse;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->user = User::factory()->create(['is_active' => true]);
        $this->user->assignRole('staff');
        $this->warehouse = Warehouse::query()->create(['name' => 'Principal', 'code' => 'MAIN']);
    }

    public function test_pos_sale_snapshots_prices_and_deducts_stock_atomically(): void
    {
        $product = $this->product(['price' => 4.5, 'cost' => 2]);
        app(RecordStockMovement::class)->handle($product, $this->warehouse, 'receipt', 10, $this->user, 'Inicial');

        $sale = app(CreatePosSale::class)->handle($this->warehouse, $this->user, [$product->id => 2], 'card', 1, ['name' => 'Ana', 'email' => '', 'phone' => ''], null);

        $this->assertSame('completed', $sale->status);
        $this->assertSame(8.0, (float) $product->balances()->value('quantity'));
        $this->assertSame(8.0, (float) $sale->total);
        $this->assertDatabaseHas('sale_items', ['sale_id' => $sale->id, 'product_name' => 'Producto', 'unit_price' => 4.5, 'unit_cost' => 2]);
        $this->assertDatabaseHas('stock_movements', ['type' => 'sale', 'quantity' => -2]);
    }

    public function test_insufficient_stock_rolls_back_sale_and_all_lines(): void
    {
        $first = $this->product(['sku' => 'ONE', 'slug' => 'one']);
        $second = $this->product(['sku' => 'TWO', 'slug' => 'two', 'name' => 'Segundo']);
        app(RecordStockMovement::class)->handle($first, $this->warehouse, 'receipt', 3, $this->user);
        app(RecordStockMovement::class)->handle($second, $this->warehouse, 'receipt', 1, $this->user);

        try {
            app(CreatePosSale::class)->handle($this->warehouse, $this->user, [$first->id => 2, $second->id => 2], 'cash');
            $this->fail('La venta debía rechazarse.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('sales', 0);
            $this->assertSame(3.0, (float) $first->balances()->value('quantity'));
            $this->assertSame(1.0, (float) $second->balances()->value('quantity'));
        }
    }

    public function test_lot_sale_uses_fefo_and_refund_restores_original_lots(): void
    {
        $product = $this->product(['tracks_lots' => true, 'requires_expiry' => true]);
        $lotStock = app(LotStock::class);
        $lotStock->receive($product, $this->warehouse, 'receipt', 2, $this->user, 'Compra', 'A', today()->addDays(2));
        $lotStock->receive($product, $this->warehouse, 'receipt', 4, $this->user, 'Compra', 'B', today()->addDays(8));
        $sale = app(CreatePosSale::class)->handle($this->warehouse, $this->user, [$product->id => 3], 'cash');

        $this->assertSame(0.0, (float) InventoryLot::query()->where('lot_number', 'A')->value('quantity'));
        $this->assertSame(3.0, (float) InventoryLot::query()->where('lot_number', 'B')->value('quantity'));

        app(RefundSale::class)->handle($sale, $this->user, 'Error de cobro');

        $this->assertSame(2.0, (float) InventoryLot::query()->where('lot_number', 'A')->value('quantity'));
        $this->assertSame(4.0, (float) InventoryLot::query()->where('lot_number', 'B')->value('quantity'));
        $this->assertSame('refunded', $sale->fresh()->status);
        $this->assertDatabaseHas('stock_movements', ['type' => 'sale_return', 'quantity' => 3]);
    }

    public function test_sales_routes_and_pos_require_module_and_permissions(): void
    {
        $this->actingAs($this->user)->get(route('sales.index'))->assertOk();
        Livewire::actingAs($this->user)->test(PosTerminal::class)->assertOk();

        $outsider = User::factory()->create(['is_active' => true]);
        $this->actingAs($outsider)->get(route('sales.index'))->assertForbidden();
    }

    private function product(array $overrides = []): Product
    {
        return Product::query()->create(array_replace([
            'name' => 'Producto', 'slug' => 'producto-'.uniqid(), 'sku' => 'SKU-'.uniqid(),
            'unit' => 'unidad', 'price' => 5, 'cost' => 2, 'is_active' => true, 'is_sellable' => true,
            'tracks_lots' => false, 'requires_expiry' => false,
        ], $overrides));
    }
}
