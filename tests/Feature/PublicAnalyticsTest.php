<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\PublicAnalyticsEvent;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_page_views_are_recorded_without_raw_ip_addresses(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.42'])
            ->withHeader('User-Agent', 'Mozilla/5.0 Mobile')
            ->get(route('home'))->assertOk();

        $event = PublicAnalyticsEvent::query()->firstOrFail();
        $this->assertSame('page_view', $event->event_type);
        $this->assertSame('mobile', $event->device_type);
        $this->assertStringNotContainsString('203.0.113.42', json_encode($event->getAttributes()));
    }

    public function test_product_impressions_can_be_recorded_and_bots_are_ignored(): void
    {
        $product = Product::query()->create(['name' => 'Tarta', 'slug' => 'tarta', 'sku' => 'T-1', 'is_active' => true, 'is_sellable' => true]);
        $this->postJson(route('public.analytics.store'), [
            'event_type' => 'product_view', 'path' => '/productos', 'label' => 'Tarta', 'product_id' => $product->id,
        ])->assertAccepted();
        $this->assertDatabaseHas('public_analytics_events', ['event_type' => 'product_view', 'product_id' => $product->id]);

        $this->withHeader('User-Agent', 'ExampleBot/1.0')->get(route('home'))->assertOk();
        $this->assertDatabaseCount('public_analytics_events', 1);
    }

    public function test_authorized_admin_can_view_and_export_analytics(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->actingAs($admin)->get(route('admin.analytics.index'))->assertOk()->assertSee('Analítica de visitas');
        $this->actingAs($admin)->get(route('admin.analytics.export'))->assertOk()->assertDownload();
    }
}
