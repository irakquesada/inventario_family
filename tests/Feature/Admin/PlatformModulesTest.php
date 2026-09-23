<?php

namespace Tests\Feature\Admin;

use App\Livewire\Admin\PlatformModules;
use App\Models\AppModule;
use App\Models\Product;
use App\Models\User;
use App\Support\ModuleManager;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PlatformModulesTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_configured_platform_owner_can_open_module_settings(): void
    {
        $this->seed(DatabaseSeeder::class);
        $otherAdmin = User::factory()->create(['email' => 'other@example.com', 'is_active' => true]);
        $otherAdmin->assignRole('admin');
        $owner = User::factory()->create(['email' => 'admin@floopware.com', 'is_active' => true]);
        $owner->assignRole('admin');

        $this->actingAs($otherAdmin)->get(route('admin.platform.modules'))->assertForbidden();
        $this->actingAs($owner)->get(route('admin.platform.modules'))->assertOk()->assertSee('Verificación del propietario');
    }

    public function test_owner_reauthenticates_and_can_disable_module_without_deleting_data(): void
    {
        $this->seed(DatabaseSeeder::class);
        $owner = User::factory()->create(['email' => 'admin@floopware.com', 'is_active' => true]);
        $owner->assignRole('admin');
        $product = Product::query()->create(['name' => 'Producto conservado', 'slug' => 'conservado', 'sku' => 'KEEP-1']);
        $states = collect(ModuleManager::MODULES)->mapWithKeys(fn ($module, $key) => [$key => $key !== 'inventory'])->all();

        Livewire::actingAs($owner)->test(PlatformModules::class)
            ->set('password', 'password')->call('unlock')->assertHasNoErrors()
            ->set('modules', $states)->call('save')->assertHasNoErrors();

        $this->assertFalse(AppModule::query()->where('key', 'inventory')->firstOrFail()->enabled);
        $this->assertDatabaseHas('products', ['id' => $product->id]);
        $this->actingAs($owner)->get(route('inventory.products.index'))->assertNotFound();
        $this->actingAs($owner)->get(route('dashboard'))->assertOk()->assertDontSee('Consultar productos');
    }

    public function test_disabled_landing_redirects_home_to_login(): void
    {
        AppModule::query()->where('key', 'landing')->update(['enabled' => false]);

        $this->get(route('home'))->assertRedirect(route('login'));
    }
}
