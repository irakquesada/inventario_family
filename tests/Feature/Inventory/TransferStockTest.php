<?php

namespace Tests\Feature\Inventory;

use App\Livewire\Inventory\TransfersIndex;
use App\Models\InventoryBalance;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Inventory\RecordStockMovement;
use App\Services\Inventory\TransferStock;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class TransferStockTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_transfers_stock_atomically_and_records_both_sides_with_the_user(): void
    {
        $user = User::factory()->create();
        $product = Product::query()->create(['name' => 'Arroz', 'slug' => 'arroz', 'sku' => 'ARR-001']);
        $from = Warehouse::query()->create(['name' => 'Principal', 'code' => 'MAIN']);
        $to = Warehouse::query()->create(['name' => 'Cocina', 'code' => 'KIT']);
        app(RecordStockMovement::class)->handle($product, $from, 'receipt', 10, $user, 'Compra inicial');

        $result = app(TransferStock::class)->handle($product, $from, $to, 4, $user, 'Reposición de cocina');

        $this->assertSame($result['out']->operation_reference, $result['in']->operation_reference);
        $this->assertNotNull($result['out']->operation_reference);
        $this->assertDatabaseHas('inventory_balances', ['product_id' => $product->id, 'warehouse_id' => $from->id, 'quantity' => 6]);
        $this->assertDatabaseHas('inventory_balances', ['product_id' => $product->id, 'warehouse_id' => $to->id, 'quantity' => 4]);
        $this->assertDatabaseHas('stock_movements', ['user_id' => $user->id, 'type' => 'transfer_out', 'quantity' => -4]);
        $this->assertDatabaseHas('stock_movements', ['user_id' => $user->id, 'type' => 'transfer_in', 'quantity' => 4]);
    }

    public function test_an_invalid_transfer_rolls_back_without_creating_half_an_operation(): void
    {
        $user = User::factory()->create();
        $product = Product::query()->create(['name' => 'Arroz', 'slug' => 'arroz', 'sku' => 'ARR-001']);
        $from = Warehouse::query()->create(['name' => 'Principal', 'code' => 'MAIN']);
        $to = Warehouse::query()->create(['name' => 'Cocina', 'code' => 'KIT']);
        app(RecordStockMovement::class)->handle($product, $from, 'receipt', 2, $user);

        try {
            app(TransferStock::class)->handle($product, $from, $to, 3, $user, 'Cantidad imposible');
            $this->fail('La transferencia debía rechazarse.');
        } catch (ValidationException) {
            // La falta de existencias es el resultado esperado.
        }

        $this->assertSame(2.0, (float) InventoryBalance::query()->whereBelongsTo($product)->whereBelongsTo($from)->value('quantity'));
        $this->assertFalse(InventoryBalance::query()->whereBelongsTo($product)->whereBelongsTo($to)->exists());
        $this->assertSame(0, StockMovement::query()->whereNotNull('operation_reference')->count());
    }

    public function test_staff_can_complete_a_transfer_from_the_live_interface(): void
    {
        $this->seed(DatabaseSeeder::class);
        $staff = User::factory()->create();
        $staff->assignRole('staff');
        $product = Product::query()->create(['name' => 'Arroz', 'slug' => 'arroz', 'sku' => 'ARR-001']);
        $from = Warehouse::query()->create(['name' => 'Principal', 'code' => 'MAIN']);
        $to = Warehouse::query()->create(['name' => 'Cocina', 'code' => 'KIT']);
        app(RecordStockMovement::class)->handle($product, $from, 'receipt', 8, $staff);

        Livewire::actingAs($staff)->test(TransfersIndex::class)
            ->call('selectProduct', $product->id)
            ->set('fromWarehouseId', $from->id)
            ->set('toWarehouseId', $to->id)
            ->set('quantity', '3')
            ->set('reason', 'Reposición diaria')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSee('Transferencia completada correctamente.');

        $this->assertDatabaseHas('inventory_balances', ['product_id' => $product->id, 'warehouse_id' => $to->id, 'quantity' => 3]);
    }

    public function test_user_without_inventory_permission_cannot_select_a_product_for_transfer(): void
    {
        $user = User::factory()->create();
        $product = Product::query()->create(['name' => 'Arroz', 'slug' => 'arroz', 'sku' => 'ARR-001']);

        Livewire::actingAs($user)->test(TransfersIndex::class)
            ->call('selectProduct', $product->id)
            ->assertForbidden();
    }
}
