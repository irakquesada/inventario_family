<section class="mx-auto max-w-4xl">
    <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-end">
        <div><p class="text-sm font-semibold text-orange-700">Preferencias personales</p><h1 class="mt-1 text-3xl font-bold">Mis notificaciones</h1><p class="mt-2 max-w-2xl text-stone-600">Decide qué avisos quieres recibir y por dónde. Estos ajustes solo afectan a tu cuenta.</p></div>
        <a href="{{ route('dashboard') }}" class="rounded-xl border border-stone-300 px-4 py-3 text-center font-semibold">Volver al panel</a>
    </div>

    @if(session('status'))<p class="mt-5 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-emerald-800">{{ session('status') }}</p>@endif

    <form wire:submit="save" class="mt-7 space-y-6">
        <section class="overflow-hidden rounded-2xl border border-stone-200 bg-white shadow-sm">
            <div class="border-b border-stone-200 p-5"><h2 class="text-xl font-bold">Tipos de alerta</h2><p class="mt-1 text-sm text-stone-500">La alerta interna aparece en el panel; el correo se envía a {{ auth()->user()->email }}.</p></div>
            <div class="divide-y divide-stone-200">
                @foreach($events as $key => $event)
                    <fieldset class="grid gap-4 p-5 md:grid-cols-[1fr_auto] md:items-center">
                        <legend class="sr-only">{{ $event['label'] }}</legend>
                        <div><strong class="text-lg">{{ $event['label'] }}</strong><p class="mt-1 text-sm text-stone-600">{{ $event['description'] }}</p></div>
                        <div class="grid grid-cols-3 gap-2 text-center text-sm">
                            <label class="cursor-pointer rounded-xl border border-stone-200 p-3"><input wire:model="preferences.{{ $key }}.database" type="checkbox" class="mb-2 block w-full"><span class="font-semibold">Panel</span></label>
                            <label class="cursor-pointer rounded-xl border border-stone-200 p-3"><input wire:model="preferences.{{ $key }}.mail" type="checkbox" class="mb-2 block w-full"><span class="font-semibold">Correo</span></label>
                            <label class="cursor-pointer rounded-xl border border-stone-200 p-3 {{ $telegramAvailable ? '' : 'opacity-50' }}"><input wire:model="preferences.{{ $key }}.telegram" type="checkbox" class="mb-2 block w-full" @disabled(!$telegramAvailable)><span class="font-semibold">Telegram</span></label>
                        </div>
                    </fieldset>
                @endforeach
            </div>
        </section>

        <section class="rounded-2xl border border-stone-200 bg-white p-5 shadow-sm">
            <div class="grid gap-5 md:grid-cols-[1fr_20rem] md:items-end"><div><h2 class="text-xl font-bold">Destino personal de Telegram</h2><p class="mt-2 text-sm leading-6 text-stone-600">Escribe el Chat ID de tu conversación con el bot. Se guarda cifrado y ningún otro administrador podrá verlo desde el panel.</p>@unless($telegramAvailable)<p class="mt-3 rounded-xl bg-amber-50 p-3 text-sm text-amber-800">Telegram todavía no está habilitado globalmente en el servidor.</p>@endunless</div><label><span class="mb-1 block font-medium">Mi Chat ID</span><input wire:model="telegramChatId" autocomplete="off" class="w-full rounded-xl border border-stone-300 px-3 py-2.5" placeholder="Ej. 123456789" @disabled(!$telegramAvailable)>@error('telegramChatId')<span class="mt-1 block text-sm text-red-600">{{ $message }}</span>@enderror</label></div>
        </section>

        <div class="rounded-2xl border border-blue-200 bg-blue-50 p-4 text-sm text-blue-900"><strong>Importante:</strong> activar correo requiere que el servidor tenga correctamente configurado su servicio SMTP. Si desactivas todos los canales para un tipo, no recibirás ese aviso.</div>
        <button class="w-full rounded-xl bg-orange-700 px-5 py-4 text-lg font-bold text-white">Guardar mis preferencias</button>
    </form>
</section>
