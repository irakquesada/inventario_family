<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

class CreateAdmin extends Command
{
    protected $signature = 'app:create-admin {--name=} {--email=}';

    protected $description = 'Crea o actualiza un usuario administrador de forma interactiva';

    public function handle(): int
    {
        $name = trim((string) ($this->option('name') ?: $this->ask('Nombre del administrador')));
        $email = mb_strtolower(trim((string) ($this->option('email') ?: $this->ask('Correo electrónico'))));
        $password = (string) $this->secret('Contraseña (mínimo 12 caracteres)');
        $confirmation = (string) $this->secret('Confirma la contraseña');

        try {
            validator(
                compact('name', 'email', 'password', 'confirmation'),
                [
                    'name' => ['required', 'string', 'max:255'],
                    'email' => ['required', 'email', 'max:255'],
                    'password' => ['required', Password::min(12)->letters()->mixedCase()->numbers(), 'same:confirmation'],
                ],
                ['password.same' => 'Las contraseñas no coinciden.'],
            )->validate();
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $messages) {
                foreach ($messages as $message) {
                    $this->error($message);
                }
            }

            return self::FAILURE;
        }

        $adminRole = Role::findOrCreate('admin');

        $user = User::query()->updateOrCreate(
            ['email' => $email],
            ['name' => $name, 'password' => Hash::make($password)],
        );
        $user->syncRoles($adminRole);

        $this->info("Administrador {$user->email} creado correctamente.");

        return self::SUCCESS;
    }
}
