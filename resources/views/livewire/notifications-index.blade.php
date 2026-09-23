<section>
    <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
        <div><p class="text-sm font-semibold text-orange-700">Historial personal</p><h1 class="mt-1 text-3xl font-bold">Notificaciones</h1><p class="mt-2 text-stone-600">Aquí permanecen todas tus alertas, incluso después de marcarlas como vistas.</p></div>
        <div class="flex flex-wrap gap-2"><a href="{{ route('profile.notifications') }}" class="rounded-xl border border-stone-300 bg-white px-4 py-3 font-semibold">Configurar avisos</a>@if($unreadCount)<button wire:click="markAllRead" wire:confirm="¿Marcar todas las notificaciones como vistas?" class="rounded-xl bg-stone-900 px-4 py-3 font-semibold text-white">Marcar todas como vistas</button>@endif</div>
    </div>

    @if(session('status'))<p class="mt-4 rounded-xl bg-emerald-50 p-3 text-emerald-800">{{ session('status') }}</p>@endif

    <div class="mt-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-[1fr_13rem_15rem_auto]">
        <label><span class="sr-only">Buscar notificaciones</span><input wire:model.live.debounce.300ms="search" type="search" placeholder="Buscar producto, reserva, cliente…" class="w-full rounded-xl border border-stone-300 bg-white px-4 py-3"></label>
        <label><span class="sr-only">Estado</span><select wire:model.live="status" class="w-full rounded-xl border border-stone-300 bg-white px-4 py-3"><option value="all">Todas</option><option value="unread">Pendientes</option><option value="read">Vistas</option></select></label>
        <label><span class="sr-only">Tipo de notificación</span><select wire:model.live="kind" class="w-full rounded-xl border border-stone-300 bg-white px-4 py-3"><option value="">Todos los tipos</option>@foreach($kinds as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></label>
        <div class="flex items-center justify-center rounded-xl border border-stone-200 bg-white px-4 py-3 text-sm"><strong>{{ $unreadCount }}</strong><span class="ml-1 text-stone-500">pendientes de {{ $totalCount }}</span></div>
    </div>

    <div class="mt-6 space-y-3">
        @forelse($notifications as $notification)
            @php($data = $notification->data)
            <article class="rounded-2xl border p-5 {{ $notification->read_at ? 'border-stone-200 bg-white' : 'border-amber-300 bg-amber-50' }}">
                <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-start">
                    <div class="min-w-0"><div class="flex flex-wrap items-center gap-2"><strong class="text-lg">{{ $data['title'] ?? $data['product_name'] ?? $kinds[$data['kind'] ?? ''] ?? 'Notificación' }}</strong><span class="rounded-full px-2 py-0.5 text-xs font-bold {{ $notification->read_at ? 'bg-stone-100 text-stone-600' : 'bg-amber-200 text-amber-900' }}">{{ $notification->read_at ? 'Vista' : 'Pendiente' }}</span></div><p class="mt-2 leading-6 text-stone-700">{{ $data['message'] ?? 'Tienes una nueva notificación.' }}</p><p class="mt-3 text-xs text-stone-500">Recibida {{ $notification->created_at->format('d/m/Y H:i') }}@if($notification->read_at) · Vista {{ $notification->read_at->format('d/m/Y H:i') }}@endif</p></div>
                    @if($notification->read_at)<button wire:click="markUnread('{{ $notification->id }}')" class="shrink-0 rounded-xl border border-stone-300 bg-white px-3 py-2 text-sm font-semibold">Marcar pendiente</button>@else<button wire:click="markRead('{{ $notification->id }}')" class="shrink-0 rounded-xl bg-stone-900 px-3 py-2 text-sm font-semibold text-white">Marcar como vista</button>@endif
                </div>
            </article>
        @empty
            <div class="rounded-2xl border border-stone-200 bg-white p-10 text-center"><strong class="text-lg">No hay resultados</strong><p class="mt-2 text-stone-500">Prueba con otro texto o cambia los filtros.</p></div>
        @endforelse
    </div>
    <div class="mt-5">{{ $notifications->links() }}</div>
</section>
