<!DOCTYPE html>
<html lang="es" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="{{ config('brand.primary_color') }}">
    <meta name="app-public" content="true">
    <title>{{ $title }} · {{ config('brand.name') }}</title>
    <meta name="description" content="{{ config('brand.name') }}: productos, contacto, horarios y reservas.">
    @vite(['resources/css/app.css','resources/js/app.js'])
</head>
<body class="bg-stone-50 text-stone-900">
    @php($publicModules = app(App\Support\ModuleManager::class))
    <header class="sticky top-0 z-40 border-b border-stone-200 bg-white/95 text-stone-900 shadow-sm backdrop-blur">
        <div class="mx-auto flex max-w-7xl items-center justify-between px-4 py-4 sm:px-6">
            <a href="{{ route('home') }}#inicio" class="text-xl font-black text-stone-950">{{ config('brand.name') }}</a>
            <nav class="hidden items-center gap-5 text-sm font-semibold text-stone-800 md:flex">
                @if($publicModules->enabled('inventory'))<a class="hover:text-orange-700" href="{{ route('public.products') }}">Productos</a>@endif
                <a class="hover:text-orange-700" href="{{ route('home') }}#horarios">Horarios</a>
                <a class="hover:text-orange-700" href="{{ route('home') }}#contacto">Contacto</a>
                @if($publicModules->enabled('reservations'))<a href="{{ route('home') }}#reservar" class="rounded-full bg-orange-700 px-4 py-2 text-white">Reservar</a>@endif
                <a href="{{ route('login') }}" class="text-orange-700">Iniciar sesión</a>
            </nav>
            @if($publicModules->enabled('reservations'))<a href="{{ route('home') }}#reservar" class="rounded-full bg-orange-700 px-4 py-2 text-sm font-bold text-white md:hidden">Reservar</a>@endif
        </div>
    </header>
    <main>{{ $slot ?? '' }}@yield('content')</main>
    <footer class="border-t border-stone-200 bg-stone-950 text-stone-300">
        @php($publicSettings = App\Models\SiteSetting::query()->first())
        <div class="mx-auto flex max-w-7xl flex-col justify-between gap-4 px-4 py-8 text-sm sm:flex-row sm:items-center sm:px-6">
            <p>© {{ now()->year }} {{ config('brand.name') }}</p>
            <div class="flex flex-wrap gap-x-5 gap-y-2">@if($publicSettings?->privacy_policy)<a href="{{ route('public.privacy') }}" class="hover:text-white">Privacidad</a>@endif @if($publicSettings?->legal_notice)<a href="{{ route('public.legal-notice') }}" class="hover:text-white">Aviso legal</a>@endif<a href="{{ route('login') }}" class="font-semibold text-white">Acceso del equipo</a></div>
        </div>
    </footer>
    <button type="button" data-scroll-top aria-label="Volver arriba" title="Volver arriba" class="fixed bottom-5 right-5 z-30 hidden size-12 rounded-full bg-orange-700 text-xl font-bold text-white shadow-lg transition hover:-translate-y-1 hover:bg-orange-600">↑</button>
</body>
</html>
