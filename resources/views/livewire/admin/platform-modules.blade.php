<section class="mx-auto max-w-4xl">
    <div><p class="text-sm font-semibold text-orange-700">Floopware · acceso restringido</p><h1 class="mt-1 text-3xl font-bold">Módulos contratados</h1><p class="mt-2 max-w-2xl text-stone-600">Activa únicamente las áreas incluidas en la implantación del cliente. Desactivar un módulo oculta sus accesos y bloquea sus rutas, pero nunca elimina sus datos.</p></div>

    @if(!$unlocked)
        <form wire:submit="unlock" class="mt-8 rounded-2xl border border-stone-200 bg-white p-6 shadow-sm"><div class="flex size-12 items-center justify-center rounded-full bg-orange-100 text-xl text-orange-800">🔒</div><h2 class="mt-4 text-xl font-bold">Verificación del propietario</h2><p class="mt-2 text-sm text-stone-600">Vuelve a escribir la contraseña de {{ config('platform.owner_email') }}. El acceso caduca automáticamente tras {{ intdiv(config('platform.module_unlock_seconds'), 60) }} minutos.</p><label class="mt-5 block"><span class="mb-1 block font-medium">Contraseña actual</span><input wire:model="password" type="password" autocomplete="current-password" class="w-full rounded-xl border border-stone-300 px-4 py-3">@error('password')<span class="mt-1 block text-sm text-red-600">{{ $message }}</span>@enderror</label><button class="mt-4 w-full rounded-xl bg-stone-900 px-5 py-3 font-bold text-white">Desbloquear configuración</button></form>
    @else
        @if(session('status'))<p class="mt-6 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-emerald-800">{{ session('status') }}</p>@endif
        <form wire:submit="save" class="mt-8 space-y-4">
            @foreach($definitions as $key => $module)
                <label class="flex cursor-pointer items-start gap-4 rounded-2xl border border-stone-200 bg-white p-5 shadow-sm transition hover:border-orange-300"><input wire:model="modules.{{ $key }}" type="checkbox" class="mt-1 size-5"><span class="flex-1"><strong class="block text-lg">{{ $module['label'] }}</strong><span class="mt-1 block text-sm leading-6 text-stone-600">{{ $module['description'] }}</span><span class="mt-2 block text-xs font-semibold text-stone-500">Clave interna: {{ $key }}</span></span></label>
            @endforeach
            <div class="rounded-2xl border border-blue-200 bg-blue-50 p-4 text-sm text-blue-900"><strong>Áreas esenciales siempre disponibles:</strong> inicio de sesión, usuarios, auditoría, historial de accesos, notificaciones y esta configuración. Las futuras áreas de nómina y tienda aparecerán aquí cuando estén implementadas.</div>
            <div class="flex flex-col gap-2 sm:flex-row"><button class="flex-1 rounded-xl bg-orange-700 px-5 py-4 text-lg font-bold text-white">Aplicar configuración</button><button type="button" wire:click="lock" class="rounded-xl border border-stone-300 bg-white px-5 py-4 font-semibold">Bloquear ahora</button></div>
        </form>
    @endif
</section>
