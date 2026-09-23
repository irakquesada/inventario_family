<?php

namespace App\Livewire\Admin;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class UsersIndex extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    public ?int $editingId = null;

    public string $name = '';

    public string $email = '';

    public string $password = '';

    public string $passwordConfirmation = '';

    public string $role = 'staff';

    public bool $isActive = true;

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function save(): void
    {
        abort_unless(auth()->user()?->can('users.manage'), 403);

        $data = $this->validate([
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->editingId)],
            'password' => [$this->editingId ? 'nullable' : 'required', 'confirmed:passwordConfirmation', Password::min(10)->letters()->mixedCase()->numbers()],
            'role' => ['required', Rule::in(['admin', 'staff'])],
            'isActive' => ['boolean'],
        ], attributes: [
            'passwordConfirmation' => 'confirmación de contraseña',
        ]);

        $wasEditing = $this->editingId !== null;
        $user = $wasEditing ? User::query()->findOrFail($this->editingId) : new User;
        $this->guardAdministrativeContinuity($user, $data['role'], $data['isActive']);

        DB::transaction(function () use ($user, $data): void {
            $user->fill([
                'name' => $data['name'],
                'email' => mb_strtolower($data['email']),
                'is_active' => $data['isActive'],
            ]);

            if ($data['password'] !== '') {
                $user->password = $data['password'];
            }

            $user->save();
            $user->syncRoles([$data['role']]);

            $employee = Employee::query()->firstOrNew(['user_id' => $user->id]);
            if (! $employee->exists) {
                $employee->employee_number = 'USR-'.str_pad((string) $user->id, 6, '0', STR_PAD_LEFT);
            }
            $employee->fill(['name' => $user->name, 'email' => $user->email, 'is_active' => $user->is_active])->save();

            if (! $user->is_active) {
                DB::table('sessions')->where('user_id', $user->id)->delete();
            }
        });

        $this->cancel();
        session()->flash('status', $wasEditing ? 'Usuario actualizado correctamente.' : 'Usuario creado correctamente.');
    }

    public function edit(int $userId): void
    {
        abort_unless(auth()->user()?->can('users.manage'), 403);
        $user = User::query()->with('roles')->findOrFail($userId);
        $this->editingId = $user->id;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->role = $user->roles->first()?->name ?? 'staff';
        $this->isActive = $user->is_active;
        $this->reset(['password', 'passwordConfirmation']);
        $this->resetValidation();
    }

    public function cancel(): void
    {
        $this->reset(['editingId', 'name', 'email', 'password', 'passwordConfirmation']);
        $this->role = 'staff';
        $this->isActive = true;
        $this->resetValidation();
    }

    private function guardAdministrativeContinuity(User $user, string $role, bool $isActive): void
    {
        if (! $user->exists || ! $user->hasRole('admin') || ($role === 'admin' && $isActive)) {
            return;
        }

        if ($user->is(auth()->user())) {
            throw ValidationException::withMessages(['role' => 'No puedes quitarte tu propio acceso de administrador.']);
        }

        $otherActiveAdmins = User::query()
            ->whereKeyNot($user->id)
            ->where('is_active', true)
            ->role('admin')
            ->exists();

        if (! $otherActiveAdmins) {
            throw ValidationException::withMessages(['role' => 'Debe permanecer al menos otro administrador activo.']);
        }
    }

    public function render()
    {
        return view('livewire.admin.users-index', [
            'users' => User::query()
                ->with('roles')
                ->when($this->search, fn ($query) => $query->where(function ($query): void {
                    $query->where('name', 'like', '%'.$this->search.'%')
                        ->orWhere('email', 'like', '%'.$this->search.'%');
                }))
                ->orderBy('name')
                ->paginate(20),
        ])->layout('layouts.app', ['title' => 'Usuarios']);
    }
}
