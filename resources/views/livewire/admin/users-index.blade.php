<section>
    <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
        <div>
            <p class="text-sm font-semibold text-orange-700">Administración</p>
            <h1 class="mt-1 text-3xl font-bold">Usuarios</h1>
            <p class="mt-2 text-stone-600">Crea cuentas, asigna su nivel de acceso y bloquea sesiones cuando sea necesario.</p>
        </div>
        <input wire:model.live.debounce.300ms="search" type="search" placeholder="Buscar nombre o correo…" class="w-full rounded-xl border border-stone-300 bg-white px-4 py-3 sm:w-96">
    </div>

    @if(session('status'))<p class="mt-4 rounded-xl bg-emerald-50 p-3 text-emerald-800">{{ session('status') }}</p>@endif

    <div class="mt-6 grid gap-6 xl:grid-cols-[minmax(0,1fr)_28rem]">
        <div class="overflow-hidden rounded-2xl border border-stone-200 bg-white">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-stone-100 text-stone-600"><tr><th class="px-4 py-3">Usuario</th><th class="px-4 py-3">Rol</th><th class="px-4 py-3">Estado</th><th class="px-4 py-3 text-right">Acción</th></tr></thead>
                    <tbody class="divide-y divide-stone-100">
                        @forelse($users as $user)
                            <tr>
                                <td class="px-4 py-4"><strong class="block">{{ $user->name }}</strong><span class="text-stone-500">{{ $user->email }}</span>@if($user->is(auth()->user()))<span class="ml-2 rounded-full bg-orange-100 px-2 py-0.5 text-xs font-semibold text-orange-800">Tú</span>@endif</td>
                                <td class="px-4 py-4">{{ $user->hasRole('admin') ? 'Administrador' : 'Staff' }}</td>
                                <td class="px-4 py-4"><span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $user->is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-stone-200 text-stone-700' }}">{{ $user->is_active ? 'Activo' : 'Bloqueado' }}</span></td>
                                <td class="px-4 py-4 text-right"><button wire:click="edit({{ $user->id }})" class="font-semibold text-orange-700">Editar</button></td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-4 py-12 text-center text-stone-500">No hay usuarios que coincidan.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="border-t border-stone-100 p-4">{{ $users->links() }}</div>
        </div>

        <aside class="h-fit rounded-2xl border border-stone-200 bg-white p-5 xl:sticky xl:top-5">
            <h2 class="text-xl font-bold">{{ $editingId ? 'Editar usuario' : 'Nuevo usuario' }}</h2>
            <p class="mt-1 text-sm text-stone-500">Las cuentas se crean sin registro público.</p>
            <form wire:submit="save" class="mt-5 space-y-4">
                <label class="block"><span class="mb-1 block font-medium">Nombre</span><input wire:model="name" autocomplete="name" class="w-full rounded-xl border border-stone-300 px-3 py-2.5">@error('name')<span class="text-sm text-red-600">{{ $message }}</span>@enderror</label>
                <label class="block"><span class="mb-1 block font-medium">Correo</span><input wire:model="email" type="email" autocomplete="username" class="w-full rounded-xl border border-stone-300 px-3 py-2.5">@error('email')<span class="text-sm text-red-600">{{ $message }}</span>@enderror</label>
                <label class="block"><span class="mb-1 block font-medium">Rol</span><select wire:model="role" class="w-full rounded-xl border border-stone-300 px-3 py-2.5"><option value="staff">Staff · inventario</option><option value="admin">Administrador · acceso completo</option></select>@error('role')<span class="text-sm text-red-600">{{ $message }}</span>@enderror</label>
                <label class="flex items-start gap-3 rounded-xl border border-stone-200 p-3"><input wire:model="isActive" type="checkbox" class="mt-1"><span><strong class="block">Usuario activo</strong><small class="text-stone-500">Al bloquearlo se cierran sus sesiones y no podrá iniciar sesión.</small></span></label>
                <label class="block"><span class="mb-1 block font-medium">{{ $editingId ? 'Nueva contraseña (opcional)' : 'Contraseña' }}</span><input wire:model="password" type="password" autocomplete="new-password" class="w-full rounded-xl border border-stone-300 px-3 py-2.5">@error('password')<span class="text-sm text-red-600">{{ $message }}</span>@enderror</label>
                <label class="block"><span class="mb-1 block font-medium">Confirmar contraseña</span><input wire:model="passwordConfirmation" type="password" autocomplete="new-password" class="w-full rounded-xl border border-stone-300 px-3 py-2.5"></label>
                <p class="text-xs text-stone-500">Mínimo 10 caracteres, con mayúsculas, minúsculas y números.</p>
                <div class="grid grid-cols-2 gap-2"><button type="button" wire:click="cancel" class="rounded-xl border border-stone-300 px-4 py-3 font-semibold">Limpiar</button><button class="rounded-xl bg-orange-700 px-4 py-3 font-semibold text-white">{{ $editingId ? 'Guardar' : 'Crear usuario' }}</button></div>
            </form>
        </aside>
    </div>
</section>
