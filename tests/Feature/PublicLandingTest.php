<?php

namespace Tests\Feature;

use App\Livewire\Admin\LandingSettings;
use App\Livewire\Public\LandingPage;
use App\Livewire\Public\ProductCatalog;
use App\Models\BusinessHour;
use App\Models\Category;
use App\Models\Product;
use App\Models\ReservationResource;
use App\Models\SiteSetting;
use App\Models\User;
use App\Notifications\NewReservationNotification;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class PublicLandingTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_request_assigns_smallest_available_resource_and_notifies_admin(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole('admin');
        BusinessHour::query()->create(['weekday' => today()->dayOfWeek, 'opens_at' => '12:00', 'closes_at' => '23:00']);
        $large = ReservationResource::query()->create(['name' => 'Mesa grande', 'code' => 'G', 'capacity' => 8]);
        $small = ReservationResource::query()->create(['name' => 'Mesa pequeña', 'code' => 'P', 'capacity' => 4]);
        Notification::fake();

        Livewire::test(LandingPage::class)
            ->set('customerName', 'María')
            ->set('customerPhone', '600000000')
            ->set('partySize', '3')
            ->set('date', today()->toDateString())
            ->set('startTime', '19:00')
            ->set('durationMinutes', '90')
            ->set('privacyAccepted', true)
            ->call('reserve')
            ->assertHasNoErrors()
            ->assertSet('reservationReference', fn ($value) => str_starts_with($value, 'RS-'));

        $this->assertDatabaseHas('reservations', [
            'reservation_resource_id' => $small->id, 'status' => 'pending', 'source' => 'public',
        ]);
        $this->assertDatabaseMissing('reservations', ['reservation_resource_id' => $large->id]);
        Notification::assertSentTo($admin, NewReservationNotification::class);
    }

    public function test_honeypot_does_not_create_a_reservation(): void
    {
        Livewire::test(LandingPage::class)->set('website', 'https://spam.example')->call('reserve')
            ->assertSet('reservationReference', 'SOLICITUD-RECIBIDA');

        $this->assertDatabaseCount('reservations', 0);
    }

    public function test_landing_content_is_public_but_editor_requires_permission(): void
    {
        SiteSetting::query()->firstOrFail()->update(['hero_title' => 'Experiencia Chetaco']);
        $this->get(route('home'))->assertOk()->assertSee('Experiencia Chetaco');

        $this->seed(DatabaseSeeder::class);
        $staff = User::factory()->create();
        $staff->assignRole('staff');
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $this->actingAs($staff)->get(route('admin.landing.settings'))->assertForbidden();
        Livewire::actingAs($admin)->test(LandingSettings::class)->assertOk();
    }

    public function test_contact_section_renders_a_map_from_the_configured_address(): void
    {
        SiteSetting::query()->firstOrFail()->update([
            'address' => "Carrer de Exemple, 10\nValència",
            'map_embed_url' => 'https://maps.google.com/example',
        ]);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Cómo llegar')
            ->assertSee('output=embed', false)
            ->assertSee('Carrer%20de%20Exemple', false);
    }

    public function test_contact_section_uses_configured_embed_and_directions_urls(): void
    {
        SiteSetting::query()->firstOrFail()->update([
            'map_embed_url' => 'https://www.google.com/maps?q=Madrid&output=embed',
            'map_directions_url' => 'https://maps.google.com/?q=Madrid',
        ]);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('https://www.google.com/maps?q=Madrid&amp;output=embed', false)
            ->assertSee('https://maps.google.com/?q=Madrid', false);
    }

    public function test_contact_section_always_has_a_map_fallback(): void
    {
        SiteSetting::query()->firstOrFail()->update([
            'address' => null,
            'map_embed_url' => null,
            'map_directions_url' => null,
        ]);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Valencia%2C%20Spain', false)
            ->assertSee('output=embed', false);
    }

    public function test_landing_keeps_subtitle_and_presentation_and_only_shows_configured_social_links(): void
    {
        SiteSetting::query()->firstOrFail()->update([
            'hero_subtitle' => 'Subtítulo visible',
            'about_text' => 'Presentación visible',
            'instagram_url' => 'https://instagram.com/example',
            'facebook_url' => null,
        ]);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Subtítulo visible')
            ->assertSee('Presentación visible')
            ->assertSee('Instagram')
            ->assertDontSee('Facebook ↗');
    }

    public function test_legal_pages_are_only_public_when_they_have_content(): void
    {
        $this->get(route('public.privacy'))->assertNotFound();
        $this->get(route('public.legal-notice'))->assertNotFound();

        SiteSetting::query()->firstOrFail()->update([
            'privacy_policy' => 'Contenido de privacidad',
            'legal_notice' => 'Contenido del aviso legal',
        ]);

        $this->get(route('public.privacy'))->assertOk()->assertSee('Contenido de privacidad');
        $this->get(route('public.legal-notice'))->assertOk()->assertSee('Contenido del aviso legal');
        $this->get(route('home'))->assertSee('Privacidad')->assertSee('Aviso legal');
    }

    public function test_catalog_only_lists_active_sellable_products_and_can_filter_them(): void
    {
        $category = Category::query()->create(['name' => 'Bebidas', 'slug' => 'bebidas']);
        Product::query()->create([
            'category_id' => $category->id, 'name' => 'Zumo natural', 'slug' => 'zumo-natural',
            'sku' => 'ZUM-1', 'price' => 3.5, 'is_active' => true, 'is_sellable' => true,
        ]);
        Product::query()->create([
            'category_id' => $category->id, 'name' => 'Producto interno', 'slug' => 'producto-interno',
            'sku' => 'INT-1', 'is_active' => true, 'is_sellable' => false,
        ]);

        $this->get(route('public.products'))->assertOk()->assertSee('Zumo natural')->assertDontSee('Producto interno');

        Livewire::test(ProductCatalog::class)
            ->set('search', 'Zumo')
            ->set('category', 'bebidas')
            ->assertSee('Zumo natural')
            ->assertDontSee('Producto interno');
    }
}
