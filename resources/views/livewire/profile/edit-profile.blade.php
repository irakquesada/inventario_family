<section class="mx-auto max-w-5xl">
    <div class="overflow-hidden rounded-3xl bg-gradient-to-br from-stone-950 via-stone-900 to-orange-950 text-white shadow-xl">
        <div class="grid gap-8 p-6 sm:p-10 lg:grid-cols-[18rem_1fr] lg:items-center">
            <div class="text-center">
                <div class="mx-auto grid size-40 place-items-center overflow-hidden rounded-full border-4 border-white/20 bg-orange-700 text-6xl font-black shadow-2xl">
                    @if($avatar)
                        <img src="{{ $avatar->temporaryUrl() }}" alt="Vista previa" class="h-full w-full object-cover">
                    @elseif($existingAvatarPath && Storage::disk('public')->exists($existingAvatarPath))
                        <img src="{{ Storage::disk('public')->url($existingAvatarPath) }}" alt="Foto de {{ $name }}" class="h-full w-full object-cover">
                    @else
                        {{ mb_strtoupper(mb_substr($name, 0, 1)) }}
                    @endif
                </div>
                <p class="mt-4 text-sm text-stone-300">Tu espacio personal dentro de {{ config('brand.name') }}</p>
            </div>
            <div><p class="text-sm font-bold uppercase tracking-[.2em] text-orange-300">Mi cuenta</p><h1 class="mt-2 text-4xl font-black">Hola, {{ $name }}</h1><p class="mt-4 max-w-2xl leading-7 text-stone-300">Personaliza cómo te ve el equipo. Estos datos son opcionales y no sustituyen tu expediente laboral protegido.</p><div class="mt-5 rounded-2xl border border-white/10 bg-white/5 p-4"><span class="text-xs uppercase tracking-wide text-stone-400">Correo de acceso</span><strong class="mt-1 block">{{ auth()->user()->email }}</strong></div></div>
        </div>
    </div>

    @if(session('status'))<p class="mt-5 rounded-xl bg-emerald-50 p-4 text-emerald-900">{{ session('status') }}</p>@endif
    <form wire:submit="save" class="mt-6 grid gap-6 rounded-3xl border border-stone-200 bg-white p-5 shadow-sm sm:p-8 lg:grid-cols-2">
        <label class="lg:col-span-2"><span class="mb-1 block font-bold">Foto de perfil <span class="font-normal text-stone-500">(opcional)</span></span><input wire:model="avatar" type="file" accept="image/jpeg,image/png,image/webp" class="block w-full rounded-xl border border-stone-300 bg-white p-2"><small class="mt-1 block text-stone-500">JPG, PNG o WebP, hasta 3 MB. Se mostrará recortada en formato circular.</small>@error('avatar')<span class="text-sm text-red-700">{{ $message }}</span>@enderror @if($existingAvatarPath)<button type="button" wire:click="removeAvatar" wire:confirm="¿Quitar tu foto de perfil?" class="mt-3 text-sm font-bold text-red-700">Eliminar foto actual</button>@endif</label>
        <label><span class="mb-1 block font-bold">Nombre visible</span><input wire:model="name" autocomplete="name" class="w-full rounded-xl border border-stone-300 px-3 py-2.5">@error('name')<span class="text-sm text-red-700">{{ $message }}</span>@enderror</label>
        <label><span class="mb-1 block font-bold">Teléfono</span><input wire:model="phone" autocomplete="tel" class="w-full rounded-xl border border-stone-300 px-3 py-2.5">@error('phone')<span class="text-sm text-red-700">{{ $message }}</span>@enderror</label>
        <label><span class="mb-1 block font-bold">Cumpleaños</span><input wire:model="birthday" type="date" class="w-full rounded-xl border border-stone-300 px-3 py-2.5">@error('birthday')<span class="text-sm text-red-700">{{ $message }}</span>@enderror</label>
        <label><span class="mb-1 block font-bold">Ciudad</span><input wire:model="city" autocomplete="address-level2" class="w-full rounded-xl border border-stone-300 px-3 py-2.5">@error('city')<span class="text-sm text-red-700">{{ $message }}</span>@enderror</label>
        <label class="lg:col-span-2"><span class="mb-1 block font-bold">Sobre mí</span><textarea wire:model="bio" rows="5" maxlength="1000" placeholder="Aficiones, intereses o algo que quieras compartir con el equipo…" class="w-full rounded-xl border border-stone-300 px-3 py-2.5"></textarea>@error('bio')<span class="text-sm text-red-700">{{ $message }}</span>@enderror</label>
        <div class="flex flex-col gap-3 border-t border-stone-200 pt-5 sm:flex-row lg:col-span-2"><button class="rounded-xl bg-orange-700 px-6 py-3 font-bold text-white">Guardar perfil</button><a href="{{ route('profile.notifications') }}" class="rounded-xl border border-stone-300 px-6 py-3 text-center font-bold">Preferencias de avisos</a></div>
    </form>
</section>
