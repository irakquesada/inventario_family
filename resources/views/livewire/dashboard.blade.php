<section>
    <p class="text-sm font-semibold text-orange-700">Resumen</p>
    <h1 class="mt-1 text-3xl font-bold">Hola, {{ auth()->user()->name }}</h1>
    <div class="mt-8 grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
        @if($moduleManager->enabled('inventory'))
        <a href="{{ route('inventory.products.index') }}" class="group rounded-2xl border border-stone-200 bg-white p-5 transition hover:-translate-y-0.5 hover:shadow-md"><p class="text-stone-600">Productos</p><p class="mt-2 text-3xl font-bold">{{ $productsCount }}</p><span class="mt-4 inline-block text-sm font-semibold text-orange-700">Consultar productos →</span></a>
        <a href="{{ route('inventory.warehouses.index') }}" class="group rounded-2xl border border-stone-200 bg-white p-5 transition hover:-translate-y-0.5 hover:shadow-md"><p class="text-stone-600">Almacenes</p><p class="mt-2 text-3xl font-bold">{{ $warehousesCount }}</p><span class="mt-4 inline-block text-sm font-semibold text-orange-700">Gestionar almacenes →</span></a>
        <a href="{{ route('inventory.movements.index') }}" class="group rounded-2xl border border-stone-200 bg-white p-5 transition hover:-translate-y-0.5 hover:shadow-md"><p class="text-stone-600">Movimientos</p><p class="mt-2 text-3xl font-bold">{{ $movementsCount }}</p><span class="mt-4 inline-block text-sm font-semibold text-orange-700">Registrar o consultar →</span></a>
        @can('inventory.adjust')<a href="{{ route('inventory.transfers.index') }}" class="group rounded-2xl border border-stone-200 bg-white p-5 transition hover:-translate-y-0.5 hover:shadow-md"><p class="text-stone-600">Transferencias</p><p class="mt-2 text-3xl font-bold">{{ $transfersCount }}</p><span class="mt-4 inline-block text-sm font-semibold text-orange-700">Mover entre almacenes →</span></a>@endcan
        @can('inventory.manage')<a href="{{ route('inventory.purchase-orders.index') }}" class="group rounded-2xl border border-stone-200 bg-white p-5 transition hover:-translate-y-0.5 hover:shadow-md"><p class="text-stone-600">Compras pendientes</p><p class="mt-2 text-3xl font-bold">{{ $pendingPurchaseOrdersCount }}</p><span class="mt-4 inline-block text-sm font-semibold text-orange-700">Gestionar compras →</span></a>@endcan
        @endif
        @if($moduleManager->enabled('reservations')) @can('reservations.view')<a href="{{ route('reservations.index') }}" class="group rounded-2xl border border-stone-200 bg-white p-5 transition hover:-translate-y-0.5 hover:shadow-md"><p class="text-stone-600">Reservas de hoy</p><p class="mt-2 text-3xl font-bold">{{ $todayReservationsCount }}</p><span class="mt-4 inline-block text-sm font-semibold text-orange-700">Abrir agenda →</span></a>@endcan @endif
    </div>
    @if($openCounts->isNotEmpty())
        <section class="mt-8"><h2 class="text-xl font-bold">Conteos en curso</h2><div class="mt-3 grid gap-3 sm:grid-cols-2">@foreach($openCounts as $count)<a href="{{ route('inventory.counts.show', $count) }}" class="rounded-2xl border border-amber-200 bg-amber-50 p-4 transition hover:shadow-md"><strong class="block">{{ $count->warehouse->name }}</strong><span class="mt-1 block text-sm text-stone-700">{{ $count->counted_items_count }}/{{ $count->items_count }} productos contados · abierto {{ $count->started_at->diffForHumans() }}</span><span class="mt-3 inline-block text-sm font-semibold text-orange-700">Continuar conteo →</span></a>@endforeach</div></section>
    @endif
    @if($expiringLots->isNotEmpty())
        <section class="mt-8"><div class="flex items-end justify-between gap-3"><div><p class="text-sm font-semibold text-red-700">Caducidad</p><h2 class="text-xl font-bold">Lotes que requieren atención</h2></div><a href="{{ route('inventory.lots.index', ['status' => 'expiring']) }}" class="text-sm font-semibold text-orange-700">Ver todos →</a></div><div class="mt-3 grid gap-3 sm:grid-cols-2">@foreach($expiringLots as $lot)<a href="{{ route('inventory.lots.index', ['search' => $lot->lot_number]) }}" class="rounded-2xl border {{ $lot->expiry_date->isPast() ? 'border-red-700 bg-red-50' : 'border-amber-200 bg-amber-50' }} p-4"><strong class="block">{{ $lot->product->name }} · {{ $lot->lot_number }}</strong><span class="mt-1 block text-sm text-stone-700">{{ $lot->warehouse->name }} · {{ number_format((float)$lot->quantity, 3, ',', '.') }} {{ $lot->product->unit }}</span><span class="mt-2 block font-semibold {{ $lot->expiry_date->isPast() ? 'text-red-700' : 'text-amber-900' }}">{{ $lot->expiry_date->isPast() ? 'Caducó' : 'Caduca' }} {{ $lot->expiry_date->format('d/m/Y') }}</span></a>@endforeach</div></section>
    @endif
    <section class="mt-8">
        <div class="flex items-center justify-between gap-3"><h2 class="text-xl font-bold">Alertas pendientes</h2><a href="{{ route('notifications.index') }}" class="text-sm font-semibold text-orange-700">Ver historial completo →</a></div>
        <div class="mt-3 space-y-3">
            @forelse($alerts as $alert)
                <article class="flex flex-col justify-between gap-3 rounded-2xl border border-amber-200 bg-amber-50 p-4 sm:flex-row sm:items-center">
                    <div><strong>{{ $alert->data['title'] ?? $alert->data['product_name'] ?? 'Notificación' }}</strong><p class="text-sm text-stone-700">{{ $alert->data['message'] ?? 'Tienes una nueva notificación.' }}</p></div>
                    <button wire:click="markNotificationRead('{{ $alert->id }}')" class="shrink-0 rounded-lg bg-white px-3 py-2 text-sm font-semibold">Marcar como vista</button>
                </article>
            @empty
                <p class="rounded-2xl border border-stone-200 bg-white p-5 text-stone-500">No tienes alertas pendientes.</p>
            @endforelse
        </div>
    </section>
</section>
