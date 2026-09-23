<?php

namespace Tests\Feature;

use App\Models\InventoryLot;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use App\Notifications\ExpiringLotNotification;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class NotifyExpiringLotsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_notifies_active_admins_once_and_ignores_lots_outside_the_threshold(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole('admin');
        $warehouse = Warehouse::query()->create(['name' => 'Principal', 'code' => 'MAIN']);
        $product = Product::query()->create([
            'name' => 'Yogur', 'slug' => 'yogur', 'sku' => 'YOG-001',
            'tracks_lots' => true, 'requires_expiry' => true, 'expiry_warning_days' => 5,
        ]);
        $due = InventoryLot::query()->create([
            'product_id' => $product->id, 'warehouse_id' => $warehouse->id,
            'lot_number' => 'CERCA', 'expiry_date' => today()->addDays(4), 'quantity' => 3,
        ]);
        $far = InventoryLot::query()->create([
            'product_id' => $product->id, 'warehouse_id' => $warehouse->id,
            'lot_number' => 'LEJOS', 'expiry_date' => today()->addDays(6), 'quantity' => 3,
        ]);
        Notification::fake();

        $this->artisan('inventory:notify-expiring-lots')->expectsOutput('Alertas de caducidad emitidas: 1')->assertSuccessful();
        $this->artisan('inventory:notify-expiring-lots')->expectsOutput('Alertas de caducidad emitidas: 0')->assertSuccessful();

        Notification::assertSentToTimes($admin, ExpiringLotNotification::class, 1);
        $this->assertNotNull($due->fresh()->expiry_notified_at);
        $this->assertNull($far->fresh()->expiry_notified_at);
    }
}
