<?php

namespace Tests\Feature\Inventory;

use App\Events\InventoryChanged;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Inventory\RecordStockMovement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class InventoryRealtimeTest extends TestCase
{
    use RefreshDatabase;

    public function test_confirmed_movement_dispatches_a_realtime_event_with_minimal_payload(): void
    {
        Event::fake([InventoryChanged::class]);
        $user = User::factory()->create();
        $product = Product::query()->create(['name' => 'Café', 'slug' => 'cafe', 'sku' => 'CAF-RT']);
        $warehouse = Warehouse::query()->create(['name' => 'Principal', 'code' => 'MAIN']);

        $movement = app(RecordStockMovement::class)->handle($product, $warehouse, 'receipt', 2, $user, 'Compra');

        Event::assertDispatched(InventoryChanged::class, fn ($event) => $event->movement->is($movement)
            && $event->broadcastAs() === 'inventory.changed'
            && $event->broadcastWith()['product_id'] === $product->id);
    }
}
