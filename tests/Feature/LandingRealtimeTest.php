<?php

namespace Tests\Feature;

use App\Events\LandingUpdated;
use App\Livewire\Admin\LandingSettings;
use App\Livewire\Public\ProductCatalog;
use App\Models\Product;
use App\Models\Testimonial;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;
use Tests\TestCase;

class LandingRealtimeTest extends TestCase
{
    use RefreshDatabase;

    public function test_landing_changes_are_broadcast_after_the_transaction(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        Event::fake([LandingUpdated::class]);

        Livewire::actingAs($admin)->test(LandingSettings::class)
            ->set('heroTitle', 'Portada actualizada')
            ->call('save')
            ->assertHasNoErrors();

        Event::assertDispatched(LandingUpdated::class);
    }

    public function test_testimonial_moderation_is_broadcast_to_the_public_landing(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $testimonial = Testimonial::query()->create([
            'customer_name' => 'Cliente',
            'rating' => 5,
            'comment' => 'Una experiencia excelente y recomendable.',
            'source' => 'qr',
            'moderation_status' => 'pending',
            'is_active' => false,
        ]);
        Event::fake([LandingUpdated::class]);

        Livewire::actingAs($admin)->test(LandingSettings::class)
            ->call('approveTestimonial', $testimonial->id)
            ->assertHasNoErrors();

        Event::assertDispatched(LandingUpdated::class);
    }

    public function test_frontend_subscribes_to_public_landing_updates(): void
    {
        $javascript = file_get_contents(resource_path('js/app.js'));

        $this->assertStringContainsString("window.Echo.channel('landing')", $javascript);
        $this->assertStringContainsString("Livewire.dispatch('landing-updated'", $javascript);
    }

    public function test_public_catalog_refreshes_product_data_when_event_arrives(): void
    {
        $product = Product::query()->create([
            'name' => 'Producto original', 'slug' => 'producto-original', 'sku' => 'RT-1',
            'price' => 4, 'is_active' => true, 'is_sellable' => true,
        ]);
        $component = Livewire::test(ProductCatalog::class)->assertSee('Producto original');
        $product->update(['name' => 'Producto recién actualizado']);

        $component->dispatch('catalog-updated')->assertSee('Producto recién actualizado')->assertDontSee('Producto original');
    }

    public function test_frontend_subscribes_to_public_catalog_updates(): void
    {
        $javascript = file_get_contents(resource_path('js/app.js'));

        $this->assertStringContainsString("window.Echo.channel('catalog')", $javascript);
        $this->assertStringContainsString("Livewire.dispatch('catalog-updated'", $javascript);
    }
}
