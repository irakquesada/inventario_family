<?php

namespace Tests\Feature\Inventory;

use App\Livewire\Inventory\SupplierProducts;
use App\Livewire\Inventory\SuppliersIndex;
use App\Models\Product;
use App\Models\ProductSupplier;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SuppliersTest extends TestCase
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

    public function test_staff_can_create_and_update_a_supplier(): void
    {
        $component = Livewire::actingAs($this->staff)->test(SuppliersIndex::class)
            ->set('name', 'Distribuciones Valencia')
            ->set('taxId', 'B12345678')
            ->set('contactName', 'María')
            ->set('email', 'compras@example.com')
            ->call('save')
            ->assertHasNoErrors();

        $supplier = Supplier::query()->where('tax_id', 'B12345678')->firstOrFail();
        $component->call('edit', $supplier->id)
            ->set('phone', '960000000')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('suppliers', [
            'id' => $supplier->id, 'name' => 'Distribuciones Valencia', 'phone' => '960000000',
        ]);
    }

    public function test_product_can_have_multiple_suppliers_but_only_one_preferred(): void
    {
        $product = Product::query()->create(['name' => 'Arroz', 'slug' => 'arroz', 'sku' => 'ARR-001']);
        $first = Supplier::query()->create(['name' => 'Proveedor uno']);
        $second = Supplier::query()->create(['name' => 'Proveedor dos']);

        Livewire::actingAs($this->staff)->test(SupplierProducts::class, ['supplier' => $first])
            ->call('selectProduct', $product->id)
            ->set('supplierSku', 'P1-ARROZ')
            ->set('unitCost', '1.25')
            ->set('minimumOrderQuantity', '10')
            ->set('leadTimeDays', '2')
            ->set('isPreferred', true)
            ->call('save')
            ->assertHasNoErrors();

        Livewire::actingAs($this->staff)->test(SupplierProducts::class, ['supplier' => $second])
            ->call('selectProduct', $product->id)
            ->set('unitCost', '1.10')
            ->set('minimumOrderQuantity', '20')
            ->set('isPreferred', true)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseCount('product_supplier', 2);
        $this->assertFalse(ProductSupplier::query()->whereBelongsTo($first)->firstOrFail()->is_preferred);
        $this->assertTrue(ProductSupplier::query()->whereBelongsTo($second)->firstOrFail()->is_preferred);
    }

    public function test_same_product_cannot_be_linked_twice_to_same_supplier(): void
    {
        $product = Product::query()->create(['name' => 'Arroz', 'slug' => 'arroz', 'sku' => 'ARR-001']);
        $supplier = Supplier::query()->create(['name' => 'Proveedor']);
        ProductSupplier::query()->create([
            'product_id' => $product->id, 'supplier_id' => $supplier->id,
            'minimum_order_quantity' => 1,
        ]);

        Livewire::actingAs($this->staff)->test(SupplierProducts::class, ['supplier' => $supplier])
            ->call('selectProduct', $product->id)
            ->call('save')
            ->assertHasErrors(['productId']);
    }

    public function test_linked_supplier_cannot_be_deleted(): void
    {
        $product = Product::query()->create(['name' => 'Arroz', 'slug' => 'arroz', 'sku' => 'ARR-001']);
        $supplier = Supplier::query()->create(['name' => 'Proveedor']);
        ProductSupplier::query()->create([
            'product_id' => $product->id, 'supplier_id' => $supplier->id,
            'minimum_order_quantity' => 1,
        ]);

        Livewire::actingAs($this->staff)->test(SuppliersIndex::class)
            ->call('delete', $supplier->id)
            ->assertHasErrors(['delete']);

        $this->assertDatabaseHas('suppliers', ['id' => $supplier->id]);
    }

    public function test_user_without_inventory_management_cannot_open_suppliers(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('inventory.suppliers.index'))->assertForbidden();
    }
}
