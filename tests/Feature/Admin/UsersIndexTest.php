<?php

namespace Tests\Feature\Admin;

use App\Livewire\Admin\UsersIndex;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class UsersIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_a_staff_user(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        Livewire::actingAs($admin)->test(UsersIndex::class)
            ->set('name', 'María Staff')
            ->set('email', 'MARIA@example.com')
            ->set('password', 'ClaveSegura2026')
            ->set('passwordConfirmation', 'ClaveSegura2026')
            ->set('role', 'staff')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSee('Usuario creado correctamente.');

        $user = User::query()->where('email', 'maria@example.com')->firstOrFail();
        $this->assertTrue($user->is_active);
        $this->assertTrue($user->hasRole('staff'));
        $this->assertDatabaseHas('employees', [
            'user_id' => $user->id, 'name' => 'María Staff', 'email' => 'maria@example.com', 'is_active' => true,
        ]);
    }

    public function test_admin_can_block_another_user_and_remove_its_sessions(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $staff = User::factory()->create();
        $staff->assignRole('staff');
        $this->actingAs($staff);
        $sessionId = session()->getId();
        session()->put('initialized', true);
        session()->save();

        Livewire::actingAs($admin)->test(UsersIndex::class)
            ->call('edit', $staff->id)
            ->set('isActive', false)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertFalse($staff->fresh()->is_active);
        $this->assertDatabaseMissing('sessions', ['id' => $sessionId]);
    }

    public function test_admin_cannot_remove_its_own_administrative_access(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        Livewire::actingAs($admin)->test(UsersIndex::class)
            ->call('edit', $admin->id)
            ->set('role', 'staff')
            ->call('save')
            ->assertHasErrors(['role']);

        $this->assertTrue($admin->fresh()->hasRole('admin'));
    }

    public function test_user_without_permission_cannot_manage_users(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('admin.users.index'))->assertForbidden();
    }
}
