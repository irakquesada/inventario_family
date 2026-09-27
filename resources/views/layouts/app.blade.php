<!DOCTYPE html>
<html lang="es" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="{{ config('brand.primary_color') }}">
    <meta name="app-authenticated" content="true">
    <meta name="app-url" content="{{ url('/') }}">
    <title>{{ $title ?? 'Panel' }} · {{ config('brand.name') }}</title>
    <script>
        (() => {
            const preference = localStorage.getItem('theme') || 'system';
            const dark = preference === 'dark' || (preference === 'system' && matchMedia('(prefers-color-scheme: dark)').matches);
            document.documentElement.classList.toggle('dark', dark);
            document.documentElement.dataset.theme = preference;
        })();
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-stone-50 text-stone-900">
    @php($moduleManager = app(App\Support\ModuleManager::class))
    <header class="border-b border-stone-200 bg-white print:hidden">
        <div class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-3 sm:px-6">
            <a href="{{ route('dashboard') }}" class="text-lg font-bold">{{ config('brand.name') }}</a>
            <nav class="hidden items-center gap-3 text-sm font-medium md:flex">
                @if($moduleManager->enabled('inventory')) @can('inventory.view')
                    <a class="rounded-lg px-3 py-2 hover:bg-stone-100" href="{{ route('inventory.products.index') }}">Inventario</a>
                @endcan @endif
                @if($moduleManager->enabled('reservations')) @can('reservations.view')<a class="rounded-lg px-3 py-2 hover:bg-stone-100" href="{{ route('reservations.index') }}">Reservas</a>@endcan @endif
                @if($moduleManager->enabled('sales')) @can('sales.view')<a class="rounded-lg px-3 py-2 hover:bg-stone-100" href="{{ route('sales.index') }}">Ventas</a>@endcan @endif
                @can('audit.view')
                    <a class="rounded-lg px-3 py-2 hover:bg-stone-100" href="{{ route('admin.access-logs.index') }}">Accesos</a>
                    <a class="rounded-lg px-3 py-2 hover:bg-stone-100" href="{{ route('admin.activity-logs.index') }}">Auditoría</a>
                    <a class="rounded-lg px-3 py-2 hover:bg-stone-100" href="{{ route('admin.analytics.index') }}">Analítica</a>
                @endcan
                @can('users.manage')
                    <a class="rounded-lg px-3 py-2 hover:bg-stone-100" href="{{ route('admin.users.index') }}">Usuarios</a>
                @endcan
                @if($moduleManager->enabled('landing')) @can('content.manage')<a class="rounded-lg px-3 py-2 hover:bg-stone-100" href="{{ route('admin.landing.settings') }}">Landing</a>@endcan @endif
                @if($moduleManager->enabled('people'))
                    @can('people.view')<a class="rounded-lg px-3 py-2 hover:bg-stone-100" href="{{ route('people.employees.index') }}">Personal</a>@endcan
                    @can('people.manage')<a class="rounded-lg px-3 py-2 hover:bg-stone-100" href="{{ route('people.attendance.index') }}">Asistencia</a>@endcan
                    @can('people.manage')<a class="rounded-lg px-3 py-2 hover:bg-stone-100" href="{{ route('people.absences.admin') }}">Ausencias</a>@endcan
                    @can('people.clock')@if(auth()->user()->employee)<a class="rounded-lg px-3 py-2 hover:bg-stone-100" href="{{ route('people.clock') }}">Fichar</a>@endif @endcan
                    @can('people.clock')@if(auth()->user()->employee)<a class="rounded-lg px-3 py-2 hover:bg-stone-100" href="{{ route('people.absences.mine') }}">Mis ausencias</a>@endif @endcan
                @endif
                @if(auth()->user()->hasRole('admin') && mb_strtolower(auth()->user()->email) === mb_strtolower(config('platform.owner_email')))<a class="rounded-lg px-3 py-2 hover:bg-stone-100" href="{{ route('admin.platform.modules') }}">Plataforma</a>@endif
                <a class="rounded-lg px-3 py-2 hover:bg-stone-100" href="{{ route('notifications.index') }}">Notificaciones @if(auth()->user()->unreadNotifications()->exists())<span class="ml-1 inline-block size-2 rounded-full bg-red-600" aria-label="Hay notificaciones pendientes"></span>@endif</a>
                <button type="button" data-theme-toggle class="rounded-lg px-3 py-2 hover:bg-stone-100" aria-label="Cambiar tema">Tema</button>
                <form method="POST" action="{{ route('logout') }}">@csrf
                    <button class="rounded-lg px-3 py-2 hover:bg-stone-100">Salir</button>
                </form>
            </nav>
            <button type="button" data-app-menu-open class="inline-flex items-center gap-2 rounded-xl border border-stone-300 bg-white px-3 py-2 font-semibold md:hidden" aria-label="Abrir menú" aria-haspopup="dialog">☰ <span>Menú</span></button>
        </div>
    </header>
    @if($moduleManager->enabled('inventory')) @can('inventory.view')
        <nav class="hidden border-b border-stone-200 bg-stone-100 print:hidden md:block" aria-label="Secciones de inventario">
            <div class="mx-auto flex max-w-7xl gap-1 overflow-x-auto px-4 py-2 text-sm sm:px-6">
                <a class="whitespace-nowrap rounded-lg px-3 py-2 hover:bg-white" href="{{ route('inventory.products.index') }}">Productos</a>
                <a class="whitespace-nowrap rounded-lg px-3 py-2 hover:bg-white" href="{{ route('inventory.categories.index') }}">Categorías</a>
                <a class="whitespace-nowrap rounded-lg px-3 py-2 hover:bg-white" href="{{ route('inventory.brands.index') }}">Marcas</a>
                <a class="whitespace-nowrap rounded-lg px-3 py-2 hover:bg-white" href="{{ route('inventory.warehouses.index') }}">Almacenes</a>
                <a class="whitespace-nowrap rounded-lg px-3 py-2 hover:bg-white" href="{{ route('inventory.movements.index') }}">Movimientos</a>
                @can('inventory.adjust')<a class="whitespace-nowrap rounded-lg px-3 py-2 hover:bg-white" href="{{ route('inventory.transfers.index') }}">Transferencias</a>@endcan
                @can('inventory.manage')<a class="whitespace-nowrap rounded-lg px-3 py-2 hover:bg-white" href="{{ route('inventory.labels.index') }}">Etiquetas</a>@endcan
                @can('inventory.count')<a class="whitespace-nowrap rounded-lg px-3 py-2 hover:bg-white" href="{{ route('inventory.counts.index') }}">Conteos</a>@endcan
                @can('inventory.manage')<a class="whitespace-nowrap rounded-lg px-3 py-2 hover:bg-white" href="{{ route('inventory.suppliers.index') }}">Proveedores</a>@endcan
                @can('inventory.manage')<a class="whitespace-nowrap rounded-lg px-3 py-2 hover:bg-white" href="{{ route('inventory.purchase-orders.index') }}">Compras</a>@endcan
                <a class="whitespace-nowrap rounded-lg px-3 py-2 hover:bg-white" href="{{ route('inventory.lots.index') }}">Lotes</a>
            </div>
        </nav>
    @endcan @endif
    <dialog data-app-menu class="m-0 ml-auto h-dvh max-h-none w-[min(88vw,24rem)] max-w-none bg-white p-0 text-stone-900 shadow-2xl backdrop:bg-black/65 print:hidden">
        <div class="flex min-h-full flex-col">
            <div class="flex items-center justify-between border-b border-stone-200 p-4"><strong class="text-lg">{{ config('brand.name') }}</strong><button type="button" data-app-menu-close class="rounded-xl border border-stone-300 px-3 py-2 font-semibold" aria-label="Cerrar menú">✕ Cerrar</button></div>
            <nav class="flex-1 overflow-y-auto p-4" aria-label="Menú principal">
                <p class="px-3 text-xs font-bold uppercase tracking-wide text-stone-500">General</p>
                <div class="mt-2 grid gap-1">
                    <a class="rounded-xl px-3 py-3 font-semibold hover:bg-stone-100" href="{{ route('dashboard') }}">Panel</a>
                    @if($moduleManager->enabled('reservations')) @can('reservations.view')<a class="rounded-xl px-3 py-3 font-semibold hover:bg-stone-100" href="{{ route('reservations.index') }}">Reservas</a>@endcan @endif
                    @if($moduleManager->enabled('sales')) @can('sales.view')<a class="rounded-xl px-3 py-3 font-semibold hover:bg-stone-100" href="{{ route('sales.index') }}">Ventas</a>@endcan @endif
                    @can('audit.view')<a class="rounded-xl px-3 py-3 font-semibold hover:bg-stone-100" href="{{ route('admin.access-logs.index') }}">Registro de accesos</a>@endcan
                    @can('audit.view')<a class="rounded-xl px-3 py-3 font-semibold hover:bg-stone-100" href="{{ route('admin.activity-logs.index') }}">Auditoría de operaciones</a><a class="rounded-xl px-3 py-3 font-semibold hover:bg-stone-100" href="{{ route('admin.analytics.index') }}">Analítica pública</a>@endcan
                    @can('users.manage')<a class="rounded-xl px-3 py-3 font-semibold hover:bg-stone-100" href="{{ route('admin.users.index') }}">Usuarios</a>@endcan
                    @if($moduleManager->enabled('landing')) @can('content.manage')<a class="rounded-xl px-3 py-3 font-semibold hover:bg-stone-100" href="{{ route('admin.landing.settings') }}">Landing pública</a>@endcan @endif
                    @if($moduleManager->enabled('people'))
                        @can('people.view')<a class="rounded-xl px-3 py-3 font-semibold hover:bg-stone-100" href="{{ route('people.employees.index') }}">Personal</a>@endcan
                        @can('people.manage')<a class="rounded-xl px-3 py-3 font-semibold hover:bg-stone-100" href="{{ route('people.attendance.index') }}">Control de asistencia</a>@endcan
                        @can('people.manage')<a class="rounded-xl px-3 py-3 font-semibold hover:bg-stone-100" href="{{ route('people.absences.admin') }}">Ausencias y vacaciones</a>@endcan
                        @can('people.clock')@if(auth()->user()->employee)<a class="rounded-xl px-3 py-3 font-semibold hover:bg-stone-100" href="{{ route('people.clock') }}">Mi fichaje</a>@endif @endcan
                        @can('people.clock')@if(auth()->user()->employee)<a class="rounded-xl px-3 py-3 font-semibold hover:bg-stone-100" href="{{ route('people.absences.mine') }}">Mis ausencias</a>@endif @endcan
                    @endif
                    @if(auth()->user()->hasRole('admin') && mb_strtolower(auth()->user()->email) === mb_strtolower(config('platform.owner_email')))<a class="rounded-xl px-3 py-3 font-semibold hover:bg-stone-100" href="{{ route('admin.platform.modules') }}">Configuración de plataforma</a>@endif
                    <a class="rounded-xl px-3 py-3 font-semibold hover:bg-stone-100" href="{{ route('notifications.index') }}">Notificaciones @if(auth()->user()->unreadNotifications()->exists())<span class="ml-1 rounded-full bg-red-100 px-2 py-0.5 text-xs text-red-800">Pendientes</span>@endif</a>
                </div>
                @if($moduleManager->enabled('inventory')) @can('inventory.view')
                    <p class="mt-6 px-3 text-xs font-bold uppercase tracking-wide text-stone-500">Inventario</p>
                    <div class="mt-2 grid gap-1">
                        <a class="rounded-xl px-3 py-3 font-semibold hover:bg-stone-100" href="{{ route('inventory.products.index') }}">Productos</a>
                        <a class="rounded-xl px-3 py-3 font-semibold hover:bg-stone-100" href="{{ route('inventory.categories.index') }}">Categorías</a>
                        <a class="rounded-xl px-3 py-3 font-semibold hover:bg-stone-100" href="{{ route('inventory.brands.index') }}">Marcas</a>
                        <a class="rounded-xl px-3 py-3 font-semibold hover:bg-stone-100" href="{{ route('inventory.warehouses.index') }}">Almacenes</a>
                        <a class="rounded-xl px-3 py-3 font-semibold hover:bg-stone-100" href="{{ route('inventory.movements.index') }}">Movimientos</a>
                        @can('inventory.adjust')<a class="rounded-xl px-3 py-3 font-semibold hover:bg-stone-100" href="{{ route('inventory.transfers.index') }}">Transferencias</a>@endcan
                        @can('inventory.manage')<a class="rounded-xl px-3 py-3 font-semibold hover:bg-stone-100" href="{{ route('inventory.labels.index') }}">Etiquetas</a>@endcan
                        @can('inventory.count')<a class="rounded-xl px-3 py-3 font-semibold hover:bg-stone-100" href="{{ route('inventory.counts.index') }}">Conteos físicos</a>@endcan
                        @can('inventory.manage')<a class="rounded-xl px-3 py-3 font-semibold hover:bg-stone-100" href="{{ route('inventory.suppliers.index') }}">Proveedores</a>@endcan
                        @can('inventory.manage')<a class="rounded-xl px-3 py-3 font-semibold hover:bg-stone-100" href="{{ route('inventory.purchase-orders.index') }}">Órdenes de compra</a>@endcan
                        <a class="rounded-xl px-3 py-3 font-semibold hover:bg-stone-100" href="{{ route('inventory.lots.index') }}">Lotes y caducidad</a>
                    </div>
                @endcan @endif
                @if($moduleManager->enabled('sales')) @can('sales.view')
                    <p class="mt-6 px-3 text-xs font-bold uppercase tracking-wide text-stone-500">Ventas</p>
                    <div class="mt-2 grid gap-1"><a class="rounded-xl px-3 py-3 font-semibold hover:bg-stone-100" href="{{ route('sales.index') }}">Historial de ventas</a>@can('sales.create')<a class="rounded-xl px-3 py-3 font-semibold hover:bg-stone-100" href="{{ route('sales.pos') }}">Punto de venta</a>@endcan</div>
                @endcan @endif
            </nav>
            <div class="space-y-2 border-t border-stone-200 p-4">
                <button type="button" data-theme-toggle class="w-full rounded-xl border border-stone-300 px-4 py-3 text-left font-semibold">Tema</button>
                <form method="POST" action="{{ route('logout') }}">@csrf<button class="w-full rounded-xl bg-red-700 px-4 py-3 text-left font-semibold text-white">Cerrar sesión</button></form>
            </div>
        </div>
    </dialog>
    <main class="mx-auto max-w-7xl px-4 py-6 print:max-w-none print:p-0 sm:px-6">{{ $slot }}</main>
    <footer class="mt-12 border-t border-stone-200 bg-white print:hidden">
        <div class="mx-auto flex max-w-7xl flex-col justify-between gap-2 px-4 py-6 text-sm text-stone-600 sm:flex-row sm:px-6">
            <p>© {{ now()->year }} {{ config('brand.name') }}</p>
            <p>Inventario y operaciones</p>
        </div>
    </footer>
    <button type="button" data-scroll-top aria-label="Volver arriba" class="fixed bottom-5 right-5 hidden size-12 rounded-full bg-orange-700 text-xl font-bold text-white shadow-lg print:hidden">↑</button>
</body>
</html>
