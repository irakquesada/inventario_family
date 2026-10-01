<?php

namespace Tests\Feature\Admin;

use App\Livewire\Admin\PlatformModules;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PlatformSeoTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_configure_public_seo_and_disable_indexing(): void
    {
        config(['platform.owner_email' => 'owner@floopware.test']);
        $this->seed(DatabaseSeeder::class);
        $owner = User::factory()->create(['email' => 'owner@floopware.test', 'password' => bcrypt('secret-pass')]);
        $owner->assignRole('admin');
        Livewire::actingAs($owner)->test(PlatformModules::class)
            ->set('password', 'secret-pass')->call('unlock')->assertHasNoErrors()
            ->set('seoSiteName', 'Fonda Juanita')->set('seoTitle', 'Fonda Juanita | Cocina tradicional')
            ->set('seoDescription', 'Restaurante de cocina tradicional con reservas y carta digital.')
            ->set('seoBusinessType', 'Restaurant')->set('seoIndexingEnabled', false)
            ->call('saveSeo')->assertHasNoErrors();
        $this->assertDatabaseHas('site_settings', ['seo_site_name' => 'Fonda Juanita', 'seo_business_type' => 'Restaurant', 'seo_indexing_enabled' => false]);
        $this->get(route('home'))->assertOk()->assertSee('noindex,nofollow', false)->assertSee('application/ld+json', false)->assertSee('Fonda Juanita | Cocina tradicional', false);
    }
}
