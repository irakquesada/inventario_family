<?php

namespace Tests\Feature\Sales;

use App\Livewire\Public\ProductCatalog;
use App\Models\InventoryBalance;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SiteSetting;
use App\Models\Warehouse;
use App\Notifications\OnlineOrderCustomerNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class OnlineStoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_stays_in_informational_mode_when_store_is_disabled(): void
    {
        Product::query()->create([
            'name' => 'Café', 'slug' => 'cafe', 'sku' => 'CAFE', 'price' => 2.5,
            'is_active' => true, 'is_sellable' => true, 'is_online_sellable' => true,
        ]);

        $this->get(route('public.products'))->assertOk()
            ->assertSee('Carta y catálogo')
            ->assertDontSee('Añadir al carrito');
    }

    public function test_customer_can_place_a_pickup_order_and_stock_is_reserved(): void
    {
        Notification::fake();
        $warehouse = Warehouse::query()->create(['name' => 'Tienda', 'code' => 'TIENDA', 'is_active' => true]);
        SiteSetting::query()->firstOrFail()->update([
            'online_store_enabled' => true,
            'online_pickup_warehouse_id' => $warehouse->id,
            'online_order_instructions' => 'Recoge en mostrador.',
        ]);
        $product = Product::query()->create([
            'name' => 'Tarta', 'slug' => 'tarta-online', 'sku' => 'TARTA-WEB', 'price' => 4.5,
            'is_active' => true, 'is_sellable' => true, 'is_online_sellable' => true,
        ]);
        InventoryBalance::query()->create(['product_id' => $product->id, 'warehouse_id' => $warehouse->id, 'quantity' => 8]);

        Livewire::test(ProductCatalog::class)
            ->call('addToCart', $product->id)
            ->set('customerName', 'Cliente Real')
            ->set('customerEmail', 'cliente@example.com')
            ->set('customerPhone', '600123123')
            ->set('pickupDate', today()->addDay()->toDateString())
            ->set('pickupTime', '18:30')
            ->set('privacyAccepted', true)
            ->call('checkout')
            ->assertHasNoErrors()
            ->assertSet('cart', []);

        $sale = Sale::query()->firstOrFail();
        $this->assertSame('online', $sale->channel);
        $this->assertSame('pickup', $sale->fulfilment_method);
        $this->assertSame('confirmed', $sale->status);
        $this->assertSame(7.0, (float) InventoryBalance::query()->firstOrFail()->quantity);
        Notification::assertSentOnDemand(OnlineOrderCustomerNotification::class);

        $this->get(route('public.orders.show', $sale->reference))
            ->assertOk()
            ->assertSee($sale->number)
            ->assertSee('Recibido')
            ->assertSee('Tarta');
    }
}
