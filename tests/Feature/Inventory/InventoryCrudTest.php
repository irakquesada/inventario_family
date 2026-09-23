<?php

namespace Tests\Feature\Inventory;

use App\Livewire\Inventory\BrandsIndex;
use App\Livewire\Inventory\CategoriesIndex;
use App\Livewire\Inventory\ProductsIndex;
use App\Livewire\Inventory\WarehousesIndex;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class InventoryCrudTest extends TestCase
{
    use RefreshDatabase;

    protected User $staff;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->staff = User::factory()->create();
        $this->staff->assignRole('staff');
    }

    public function test_staff_can_create_reference_data(): void
    {
        Livewire::actingAs($this->staff)->test(CategoriesIndex::class)
            ->set('name', 'Bebidas')
            ->call('save')
            ->assertHasNoErrors();

        Livewire::actingAs($this->staff)->test(BrandsIndex::class)
            ->set('name', 'Chetaco')
            ->call('save')
            ->assertHasNoErrors();

        Livewire::actingAs($this->staff)->test(WarehousesIndex::class)
            ->set('name', 'Almacén principal')
            ->set('code', 'main')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('categories', ['name' => 'Bebidas']);
        $this->assertDatabaseHas('brands', ['name' => 'Chetaco']);
        $this->assertDatabaseHas('warehouses', ['code' => 'MAIN']);
    }

    public function test_staff_can_create_and_update_a_product(): void
    {
        $category = Category::query()->create(['name' => 'Bebidas', 'slug' => 'bebidas']);
        $brand = Brand::query()->create(['name' => 'Chetaco', 'slug' => 'chetaco']);

        $component = Livewire::actingAs($this->staff)->test(ProductsIndex::class)
            ->set('name', 'Agua mineral')
            ->set('sku', 'agua-001')
            ->set('categoryId', $category->id)
            ->set('brandId', $brand->id)
            ->set('minimumStock', '5')
            ->call('save')
            ->assertHasNoErrors();

        $product = Product::query()->where('sku', 'AGUA-001')->firstOrFail();

        $component->call('edit', $product->id)
            ->set('name', 'Agua mineral 500 ml')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('products', ['id' => $product->id, 'name' => 'Agua mineral 500 ml']);
    }

    public function test_new_product_can_record_auditable_opening_stock(): void
    {
        $warehouse = Warehouse::query()->create(['name' => 'Principal', 'code' => 'MAIN']);

        Livewire::actingAs($this->staff)->test(ProductsIndex::class)
            ->set('name', 'Arroz')
            ->set('sku', 'ARR-001')
            ->set('initialQuantity', '25')
            ->set('initialWarehouseId', $warehouse->id)
            ->set('initialReason', 'Inventario de apertura')
            ->call('save')
            ->assertHasNoErrors();

        $product = Product::query()->where('sku', 'ARR-001')->firstOrFail();
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'user_id' => $this->staff->id,
            'type' => 'opening_balance',
            'quantity' => 25,
            'reason' => 'Inventario de apertura',
        ]);
    }

    public function test_user_without_inventory_permission_cannot_open_catalogs(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('inventory.products.index'))->assertForbidden();
        $this->actingAs($user)->get(route('inventory.categories.index'))->assertForbidden();
    }

    public function test_product_with_stock_cannot_be_deleted(): void
    {
        $product = Product::query()->create(['name' => 'Naranjas', 'slug' => 'naranjas', 'sku' => 'NAR-001']);
        $warehouse = Warehouse::query()->create(['name' => 'Principal', 'code' => 'MAIN']);
        $product->balances()->create(['warehouse_id' => $warehouse->id, 'quantity' => 2]);

        Livewire::actingAs($this->staff)->test(ProductsIndex::class)
            ->call('delete', $product->id)
            ->assertHasErrors('delete');

        $this->assertDatabaseHas('products', ['id' => $product->id]);
    }

    public function test_products_can_be_filtered_by_aggregated_low_stock(): void
    {
        $warehouse = Warehouse::query()->create(['name' => 'Principal', 'code' => 'MAIN']);
        $low = Product::query()->create(['name' => 'Tomates bajos', 'slug' => 'tomates-bajos', 'sku' => 'TOM-LOW', 'minimum_stock' => 5]);
        $enough = Product::query()->create(['name' => 'Arroz suficiente', 'slug' => 'arroz-suficiente', 'sku' => 'ARR-OK', 'minimum_stock' => 5]);
        $low->balances()->create(['warehouse_id' => $warehouse->id, 'quantity' => 3]);
        $enough->balances()->create(['warehouse_id' => $warehouse->id, 'quantity' => 12]);

        Livewire::actingAs($this->staff)->test(ProductsIndex::class)
            ->assertSee('Tomates bajos')->assertSee('Arroz suficiente')
            ->call('toggleLowStock')
            ->assertSee('Tomates bajos')->assertDontSee('Arroz suficiente')
            ->assertSee('Mostrando stock bajo');
    }
}
