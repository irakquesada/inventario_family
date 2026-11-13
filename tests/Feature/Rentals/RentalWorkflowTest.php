<?php

namespace Tests\Feature\Rentals;

use App\Models\InventoryBalance;
use App\Models\Product;
use App\Models\RentalRate;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Rentals\ManageRental;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class RentalWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_rental_blocks_availability_and_calculates_return_by_elapsed_time(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $this->actingAs($admin);
        $warehouse = Warehouse::query()->create(['name' => 'Tienda', 'code' => 'RENT', 'is_active' => true]);
        $product = Product::query()->create(['name' => 'Bicicleta', 'slug' => 'bicicleta', 'sku' => 'BIKE-1', 'is_active' => true, 'is_rentable' => true]);
        InventoryBalance::query()->create(['product_id' => $product->id, 'warehouse_id' => $warehouse->id, 'quantity' => 1]);
        $rate = RentalRate::query()->create(['product_id' => $product->id, 'name' => 'Libre por hora', 'billing_unit' => 'hour', 'unit_minutes' => 60, 'price' => 10, 'minimum_units' => 1, 'grace_minutes' => 5, 'is_active' => true]);
        $service = app(ManageRental::class);
        $rental = $service->start($rate, $warehouse, $admin, ['name' => 'Cliente', 'email' => '', 'phone' => ''], 1, '2026-11-10 10:00', null, null);

        try {
            $service->start($rate, $warehouse, $admin, ['name' => 'Otro', 'email' => '', 'phone' => ''], 1, '2026-11-10 10:30', null, null);
            $this->fail('Se permitió alquilar una unidad sin disponibilidad.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('quantity', $exception->errors());
        }

        $returned = $service->finish($rental, $admin, '2026-11-10 11:35', 'Devuelta correctamente.');
        $this->assertSame('returned', $returned->status);
        $this->assertSame(2, $returned->billed_units);
        $this->assertSame(20.0, (float) $returned->final_total);
        $next = $service->start($rate, $warehouse, $admin, ['name' => 'Siguiente', 'email' => '', 'phone' => ''], 1, '2026-11-10 12:00', null, null);
        $this->assertSame('active', $next->status);
    }
}
