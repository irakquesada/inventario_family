<?php

namespace Tests\Feature;

use App\Livewire\NotificationsIndex;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use App\Notifications\LowStockNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class NotificationsIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_notification_history_keeps_read_items_and_can_filter_them(): void
    {
        $user = User::factory()->create();
        $warehouse = Warehouse::query()->create(['name' => 'Principal', 'code' => 'PRI']);
        $product = Product::query()->create(['name' => 'Tomates', 'slug' => 'tomates', 'sku' => 'TOM-1', 'minimum_stock' => 5]);
        $user->notify(new LowStockNotification($product, $warehouse, 2));
        $notification = $user->notifications()->firstOrFail();

        Livewire::actingAs($user)->test(NotificationsIndex::class)
            ->assertSee('Tomates')
            ->set('search', 'Tomates')->set('kind', 'low_stock')->assertSee('Tomates')
            ->call('markRead', $notification->id)
            ->set('status', 'read')->assertSee('Tomates')
            ->set('status', 'unread')->assertDontSee('Tomates');

        $this->assertDatabaseHas('notifications', ['id' => $notification->id]);
        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_user_cannot_change_another_users_notification(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $warehouse = Warehouse::query()->create(['name' => 'Principal', 'code' => 'PRI']);
        $product = Product::query()->create(['name' => 'Arroz', 'slug' => 'arroz', 'sku' => 'ARR-1']);
        $owner->notify(new LowStockNotification($product, $warehouse, 0));

        Livewire::actingAs($intruder)->test(NotificationsIndex::class)
            ->call('markRead', $owner->notifications()->firstOrFail()->id)
            ->assertNotFound();
    }
}
