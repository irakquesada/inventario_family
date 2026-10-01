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

    public function test_public_storage_urls_do_not_expose_the_configured_application_host(): void
    {
        $this->assertSame('/storage', config('filesystems.disks.public.url'));
        $this->assertSame('/storage/products/example.jpg', Storage::disk('public')->url('products/example.jpg'));
    }

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

    public function test_catalog_replaces_a_missing_product_image_with_a_safe_fallback(): void
    {
        Storage::fake('public');
        Product::query()->create([
            'name' => 'Producto sin foto', 'slug' => 'producto-sin-foto', 'sku' => 'SIN-FOTO',
            'is_active' => true, 'is_sellable' => true, 'landing_image_path' => 'products/no-existe.webp',
        ]);

        $this->get(route('public.products'))
            ->assertOk()
            ->assertSee('Imagen no disponible')
            ->assertDontSee('data-product-image-open', false);
    }

    public function test_catalog_image_opens_the_shared_zoom_dialog(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('products/con-foto.webp', 'image-content');
        Product::query()->create([
            'name' => 'Producto con foto', 'slug' => 'producto-con-foto', 'sku' => 'CON-FOTO',
            'is_active' => true, 'is_sellable' => true, 'landing_image_path' => 'products/con-foto.webp',
        ]);

        $this->get(route('public.products'))
            ->assertOk()
            ->assertSee('data-product-image-open', false)
            ->assertSee('data-product-image-dialog', false)
            ->assertSee('data-image-zoom-in', false)
            ->assertSee('object-contain', false);
    }

    public function test_existing_product_image_uses_a_current_origin_url_in_the_edit_form(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('products/editable.jpg', 'image-content');
        $this->seed(DatabaseSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $product = Product::query()->create([
            'name' => 'Producto editable', 'slug' => 'producto-editable', 'sku' => 'EDITABLE',
            'is_active' => true, 'is_sellable' => true, 'landing_image_path' => 'products/editable.jpg',
        ]);

        Livewire::actingAs($admin)->test(ProductsIndex::class)
            ->call('edit', $product->id)
            ->assertSeeHtml('src="/storage/products/editable.jpg"')
            ->assertDontSeeHtml('src="http://localhost');
    }
}
