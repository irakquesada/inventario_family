<?php

namespace Tests\Feature\Inventory;

use App\Livewire\Inventory\ProductsIndex;
use App\Models\Product;
use App\Models\SiteSetting;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class ProductLandingTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_can_be_featured_with_image_without_being_sellable(): void
    {
        Storage::fake('public');
        $this->seed(DatabaseSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        Livewire::actingAs($admin)->test(ProductsIndex::class)
            ->set('name', 'Tarta de queso')
            ->set('sku', 'TAR-LANDING')
            ->set('featuredOnLanding', true)
            ->set('isSellable', false)
            ->set('landingImage', UploadedFile::fake()->image('tarta.webp', 1000, 700))
            ->call('save')
            ->assertHasNoErrors();

        $product = Product::query()->where('sku', 'TAR-LANDING')->firstOrFail();
        $this->assertTrue($product->featured_on_landing);
        $this->assertFalse($product->is_sellable);
        Storage::disk('public')->assertExists($product->landing_image_path);
    }

    public function test_enabled_carousel_is_rendered_only_when_a_featured_product_has_an_image(): void
    {
        SiteSetting::query()->firstOrFail()->update(['carousel_enabled' => true]);
        Product::query()->create([
            'name' => 'Tarta', 'slug' => 'tarta', 'sku' => 'TAR-1', 'is_active' => true,
            'is_sellable' => false, 'featured_on_landing' => true, 'landing_image_path' => 'products/tarta.webp',
        ]);

        $this->get(route('home'))->assertOk()->assertSee('data-product-carousel', false)->assertSee('Tarta');

        SiteSetting::query()->firstOrFail()->update(['carousel_enabled' => false]);
        $this->get(route('home'))->assertOk()->assertDontSee('data-product-carousel', false)->assertSee('Tarta');
    }
}
