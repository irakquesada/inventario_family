<section>
    <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
        <div><p class="text-sm font-semibold text-orange-700">Auditoría</p><h1 class="mt-1 text-3xl font-bold">Registro de accesos</h1><p class="mt-2 text-stone-600">Inicios y cierres de sesión de la aplicación.</p></div>
        <div class="flex flex-col gap-2 sm:flex-row">
            <input wire:model.live.debounce.300ms="search" type="search" placeholder="Usuario o correo…" class="rounded-xl border border-stone-300 bg-white px-4 py-2.5">
            <select wire:model.live="event" class="rounded-xl border border-stone-300 bg-white px-4 py-2.5"><option value="">Todos</option><option value="login">Entradas</option><option value="logout">Salidas</option></select>
        </div>
    </div>
    <div class="mt-6 overflow-hidden rounded-2xl border border-stone-200 bg-white"><div class="overflow-x-auto"><table class="w-full text-left text-sm">
        <thead class="bg-stone-100 text-stone-600"><tr><th class="px-4 py-3">Fecha</th><th class="px-4 py-3">Usuario</th><th class="px-4 py-3">Evento</th><th class="px-4 py-3">Dirección IP</th><th class="px-4 py-3">Dispositivo</th></tr></thead>
        <tbody class="divide-y divide-stone-100">@forelse($logs as $log)<tr><td class="whitespace-nowrap px-4 py-4">{{ $log->occurred_at->format('d/m/Y H:i:s') }}</td><td class="px-4 py-4"><strong class="block">{{ $log->user?->name ?? 'Usuario eliminado' }}</strong><span class="text-stone-500">{{ $log->user?->email }}</span></td><td class="px-4 py-4"><span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $log->event === 'login' ? 'bg-emerald-100 text-emerald-800' : 'bg-stone-200 text-stone-700' }}">{{ $log->event === 'login' ? 'Entrada' : 'Salida' }}</span></td><td class="px-4 py-4 font-mono text-xs">{{ $log->ip_address ?? '—' }}</td><td class="max-w-xs truncate px-4 py-4 text-stone-600" title="{{ $log->user_agent }}">{{ $log->user_agent ?? '—' }}</td></tr>@empty<tr><td colspan="5" class="px-4 py-12 text-center text-stone-500">Todavía no hay registros.</td></tr>@endforelse</tbody>
    </table></div><div class="border-t border-stone-100 p-4">{{ $logs->links() }}</div></div>
</section>
