<?php

namespace Tests\Feature\Rentals;

use App\Livewire\Public\SubmitRentalRequest;
use App\Livewire\Rentals\RentalsIndex;
use App\Models\InventoryBalance;
use App\Models\Product;
use App\Models\Rental;
use App\Models\RentalInvitation;
use App\Models\RentalRate;
use App\Models\User;
use App\Models\Warehouse;
use App\Notifications\RentalConfirmationNotification;
use App\Services\Rentals\ChargeRental;
use App\Services\Rentals\ManageRental;
use App\Services\Sales\RefundSale;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
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

    public function test_public_qr_creates_a_reserved_request_for_admin_review(): void
    {
        $this->seed(DatabaseSeeder::class);
        $warehouse = Warehouse::query()->create(['name' => 'Tienda', 'code' => 'QR', 'is_active' => true]);
        $product = Product::query()->create(['name' => 'Patinete', 'slug' => 'patinete', 'sku' => 'SCOOT-1', 'is_active' => true, 'is_rentable' => true]);
        InventoryBalance::query()->create(['product_id' => $product->id, 'warehouse_id' => $warehouse->id, 'quantity' => 2]);
        $rate = RentalRate::query()->create(['product_id' => $product->id, 'name' => 'Día completo', 'billing_unit' => 'day', 'unit_minutes' => 1440, 'price' => 25, 'minimum_units' => 1, 'grace_minutes' => 10, 'is_active' => true]);
        $token = str_repeat('q', 64);
        RentalInvitation::query()->create(['token_hash' => hash('sha256', $token), 'max_submissions' => 1, 'expires_at' => now()->addDay()]);

        Livewire::test(SubmitRentalRequest::class, ['token' => $token])
            ->set('rentalRateId', (string) $rate->id)->set('warehouseId', (string) $warehouse->id)
            ->set('customerName', 'Laura Cliente')->set('customerEmail', 'laura@example.com')->set('customerPhone', '600000000')
            ->set('quantity', '1')->set('startedAt', now()->addHours(2)->format('Y-m-d\TH:i'))
            ->set('plannedEndsAt', now()->addDay()->addHours(2)->format('Y-m-d\TH:i'))->set('privacyAccepted', true)
            ->call('submit')->assertHasNoErrors()->assertSet('submitted', true);

        $this->assertDatabaseHas('rentals', ['status' => 'reserved', 'source' => 'qr', 'customer_email' => 'laura@example.com']);
        $this->assertSame(1, RentalInvitation::query()->first()->submissions_count);

        Notification::fake();
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $rental = Rental::query()->firstOrFail();
        Livewire::actingAs($admin)->test(RentalsIndex::class)->call('confirmAndNotify', $rental->id)->assertHasNoErrors();
        Notification::assertSentOnDemand(RentalConfirmationNotification::class);
        $this->assertNotNull($rental->fresh()->confirmation_sent_at);
    }

    public function test_pos_can_take_deposit_and_settle_rental_without_touching_stock(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $this->actingAs($admin);
        $warehouse = Warehouse::query()->create(['name' => 'Tienda', 'code' => 'PAY', 'is_active' => true]);
        $product = Product::query()->create(['name' => 'Bicicleta', 'slug' => 'bike-pay', 'sku' => 'BIKE-PAY', 'is_active' => true, 'is_rentable' => true]);
        InventoryBalance::query()->create(['product_id' => $product->id, 'warehouse_id' => $warehouse->id, 'quantity' => 1]);
        $rate = RentalRate::query()->create(['product_id' => $product->id, 'name' => 'Dos días', 'billing_unit' => 'day', 'unit_minutes' => 1440, 'price' => 20, 'minimum_units' => 1, 'grace_minutes' => 0, 'is_active' => true]);
        $rental = app(ManageRental::class)->start($rate, $warehouse, $admin, ['name' => 'Cliente', 'email' => '', 'phone' => ''], 1, '2026-11-10 10:00', '2026-11-12 10:00', null, 'reserved');

        $deposit = app(ChargeRental::class)->handle($rental, $admin, 10, 'card');
        $this->assertSame('partial', $rental->fresh()->paymentStatus());
        $this->assertSame('rental', $deposit->channel);
        $this->assertDatabaseCount('stock_movements', 0);

        $settlement = app(ChargeRental::class)->handle($rental, $admin, 30, 'cash');
        $this->assertSame('paid', $rental->fresh()->paymentStatus());
        app(RefundSale::class)->handle($settlement, $admin, 'Cobro duplicado');
        $this->assertSame('partial', $rental->fresh()->paymentStatus());
        $this->assertDatabaseCount('stock_movements', 0);
        $this->assertSame(1.0, (float) $product->balances()->value('quantity'));
    }
}
