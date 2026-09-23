<?php

namespace Tests\Feature\Admin;

use App\Livewire\Admin\ActivityLogsIndex;
use App\Models\ActivityLog;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ActivityLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_update_and_delete_are_recorded_with_the_authenticated_user(): void
    {
        $admin = User::factory()->create();
        $this->actingAs($admin);
        $product = Product::query()->create(['name' => 'Café', 'slug' => 'cafe', 'sku' => 'CAF-1']);
        $product->update(['name' => 'Café premium']);
        $product->delete();

        $logs = ActivityLog::query()->where('subject_type', Product::class)->orderBy('id')->get();
        $this->assertSame(['created', 'updated', 'deleted'], $logs->pluck('action')->all());
        $this->assertSame($admin->id, $logs[1]->user_id);
        $this->assertSame('Café', $logs[1]->before['name']);
        $this->assertSame('Café premium', $logs[1]->after['name']);
    }

    public function test_sensitive_user_fields_are_never_stored(): void
    {
        User::factory()->create(['password' => 'secret-value']);
        $log = ActivityLog::query()->where('subject_type', User::class)->latest('id')->firstOrFail();

        $this->assertArrayNotHasKey('password', $log->after);
        $this->assertArrayNotHasKey('remember_token', $log->after);
    }

    public function test_only_authorized_users_can_view_operational_audit(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $staff = User::factory()->create();
        $staff->assignRole('staff');

        Livewire::actingAs($admin)->test(ActivityLogsIndex::class)->assertOk();
        $this->actingAs($staff)->get(route('admin.activity-logs.index'))->assertForbidden();
    }
}
