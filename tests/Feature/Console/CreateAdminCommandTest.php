<?php

namespace Tests\Feature\Console;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateAdminCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_an_admin_interactively(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->artisan('app:create-admin')
            ->expectsQuestion('Nombre del administrador', 'Ada Admin')
            ->expectsQuestion('Correo electrónico', 'admin@example.com')
            ->expectsQuestion('Contraseña (mínimo 12 caracteres)', 'SecurePassword123')
            ->expectsQuestion('Confirma la contraseña', 'SecurePassword123')
            ->expectsOutput('Administrador admin@example.com creado correctamente.')
            ->assertSuccessful();

        $user = User::query()->where('email', 'admin@example.com')->firstOrFail();

        $this->assertTrue($user->hasRole('admin'));
    }
}
