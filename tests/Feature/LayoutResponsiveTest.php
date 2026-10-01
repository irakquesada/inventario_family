<?php

namespace Tests\Feature;

use App\Models\SiteSetting;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class LayoutResponsiveTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_layout_contains_the_mobile_menu_and_all_authorized_sections(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->actingAs($admin)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('data-app-menu-open', false)
            ->assertSee('data-app-menu-close', false)
            ->assertSee('Productos')
            ->assertSee('Transferencias')
            ->assertSee('Etiquetas')
            ->assertSee('Registro de accesos')
            ->assertSee('Notificaciones')
            ->assertSee('Usuarios');
    }

    public function test_dark_theme_defines_contrast_for_light_status_badges(): void
    {
        $css = file_get_contents(resource_path('css/app.css'));

        $this->assertStringContainsString('html.dark .bg-amber-100', $css);
        $this->assertStringContainsString('html.dark .bg-red-100', $css);
        $this->assertStringContainsString('html.dark .text-amber-900', $css);
        $this->assertStringContainsString('html.dark .text-blue-900', $css);
    }

    public function test_navigation_groups_modules_and_shows_numeric_notification_counter(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $admin->notifications()->create(['id' => (string) Str::uuid(), 'type' => 'TestNotification', 'data' => ['title' => 'Pendiente']]);

        $this->actingAs($admin)->get(route('dashboard'))->assertOk()
            ->assertSee('Inventario ▾')->assertSee('Operaciones ▾')->assertSee('Equipo ▾')->assertSee('Administración ▾')
            ->assertSee('Notificaciones, 1 pendientes', false);
    }

    public function test_public_layout_has_a_mobile_menu_and_hides_reservations_when_disabled(): void
    {
        SiteSetting::query()->firstOrFail()->update(['reservations_enabled' => false]);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('data-app-menu-open', false)
            ->assertSee('data-app-menu-close', false)
            ->assertSee('data-public-reservation-link', false)
            ->assertSee('nav_reservar', false)
            ->assertSee('hidden', false);
    }
}
