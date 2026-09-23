<?php

namespace Tests\Feature\Inventory;

use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Inventory\RecordStockMovement;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class RecordStockMovementTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_records_movement_and_updates_balance(): void
    {
        $product = Product::query()->create(['name' => 'Naranjas', 'slug' => 'naranjas', 'sku' => 'NAR-001']);
        $warehouse = Warehouse::query()->create(['name' => 'Principal', 'code' => 'MAIN']);

        $movement = app(RecordStockMovement::class)->handle($product, $warehouse, 'receipt', 12.5);

        $this->assertSame('12.500', $movement->balance_after);
        $this->assertDatabaseHas('inventory_balances', [
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'quantity' => 12.5,
        ]);
    }

    public function test_it_rejects_negative_stock(): void
    {
        $product = Product::query()->create(['name' => 'Limones', 'slug' => 'limones', 'sku' => 'LIM-001']);
        $warehouse = Warehouse::query()->create(['name' => 'Principal', 'code' => 'MAIN']);

        $this->expectException(ValidationException::class);
        app(RecordStockMovement::class)->handle($product, $warehouse, 'issue', -1);
    }

    public function test_it_notifies_admins_only_when_watched_product_crosses_minimum_stock(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $product = Product::query()->create([
            'name' => 'Tomates',
            'slug' => 'tomates',
            'sku' => 'TOM-001',
            'minimum_stock' => 5,
            'notify_low_stock' => true,
        ]);
        $warehouse = Warehouse::query()->create(['name' => 'Principal', 'code' => 'MAIN']);
        $service = app(RecordStockMovement::class);

        $service->handle($product, $warehouse, 'receipt', 10);
        $this->assertCount(0, $admin->notifications);

        $service->handle($product, $warehouse, 'issue', -5);

        $notification = $admin->fresh()->notifications()->firstOrFail();
        $this->assertSame('low_stock', $notification->data['kind']);
        $this->assertSame('Tomates', $notification->data['product_name']);
    }

    public function test_low_stock_notification_can_use_telegram_channel(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);
        config()->set('services.telegram', [
            'enabled' => true,
            'bot_token' => 'test-token',
            'chat_id' => '123456',
        ]);
        $this->seed(DatabaseSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $product = Product::query()->create([
            'name' => 'Tomates', 'slug' => 'tomates', 'sku' => 'TOM-001',
            'minimum_stock' => 5, 'notify_low_stock' => true,
        ]);
        $warehouse = Warehouse::query()->create(['name' => 'Principal', 'code' => 'MAIN']);

        $service = app(RecordStockMovement::class);
        $service->handle($product, $warehouse, 'receipt', 10);
        $service->handle($product, $warehouse, 'issue', -5);

        Http::assertSent(fn ($request) => $request->url() === 'https://api.telegram.org/bottest-token/sendMessage'
            && $request['chat_id'] === '123456'
            && str_contains($request['text'], 'STOCK BAJO'));
    }
}
