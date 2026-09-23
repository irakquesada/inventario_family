<?php

namespace Tests\Feature\Inventory;

use App\Livewire\Inventory\MovementsIndex;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MovementsIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_record_entry_and_exit_with_its_user(): void
    {
        $this->seed(DatabaseSeeder::class);
        $staff = User::factory()->create();
        $staff->assignRole('staff');
        $product = Product::query()->create(['name' => 'Harina', 'slug' => 'harina', 'sku' => 'HAR-001']);
        $warehouse = Warehouse::query()->create(['name' => 'Principal', 'code' => 'MAIN']);

        $component = Livewire::actingAs($staff)->test(MovementsIndex::class)
            ->call('selectProduct', $product->id, 'receipt')
            ->set('warehouseId', $warehouse->id)
            ->set('quantity', '10')
            ->set('reason', 'Compra a proveedor')
            ->call('save')
            ->assertHasNoErrors();

        $component->call('selectProduct', $product->id, 'issue')
            ->set('warehouseId', $warehouse->id)
            ->set('quantity', '2')
            ->set('reason', 'Consumo de cocina')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'user_id' => $staff->id,
            'type' => 'receipt',
            'quantity' => 10,
        ]);
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'user_id' => $staff->id,
            'type' => 'issue',
            'quantity' => -2,
            'balance_after' => 8,
        ]);
    }

    public function test_user_without_adjust_permission_cannot_record_movements(): void
    {
        $user = User::factory()->create();
        $product = Product::query()->create(['name' => 'Harina', 'slug' => 'harina', 'sku' => 'HAR-001']);

        Livewire::actingAs($user)->test(MovementsIndex::class)
            ->call('selectProduct', $product->id, 'receipt')
            ->assertForbidden();
    }

    public function test_operator_can_cancel_selected_movement(): void
    {
        $this->seed(DatabaseSeeder::class);
        $staff = User::factory()->create();
        $staff->assignRole('staff');
        $product = Product::query()->create(['name' => 'Harina', 'slug' => 'harina', 'sku' => 'HAR-001']);

        Livewire::actingAs($staff)->test(MovementsIndex::class)
            ->call('selectProduct', $product->id, 'issue')
            ->set('quantity', '3')
            ->set('reason', 'Prueba')
            ->call('cancelSelection')
            ->assertSet('selectedProductId', null)
            ->assertSet('quantity', '')
            ->assertSet('reason', '')
            ->assertSet('type', 'receipt');
    }
}
