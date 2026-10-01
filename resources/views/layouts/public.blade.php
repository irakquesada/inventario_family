<!DOCTYPE html>
<html lang="es" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="{{ config('brand.primary_color') }}">
    <meta name="app-public" content="true">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="analytics-endpoint" content="{{ route('public.analytics.store') }}">
    @php($seoSettings = App\Models\SiteSetting::query()->first())
    @php($seoSiteName = $seoSettings?->seo_site_name ?: 'Inventario Pro')
    @php($seoTitle = request()->routeIs('home') ? ($seoSettings?->seo_title ?: 'Inventario Pro | Gestión, productos y reservas') : ($title.' · '.$seoSiteName))
    @php($seoDescription = $seoSettings?->seo_description ?: 'Inventario Pro centraliza productos, disponibilidad, reservas y atención al cliente en una experiencia rápida y clara.')
    @php($seoCanonical = request()->routeIs('home') && $seoSettings?->seo_canonical_url ? rtrim($seoSettings->seo_canonical_url, '/') : url()->current())
    <title>{{ $seoTitle }}</title>
    <meta name="description" content="{{ $seoDescription }}">
    @if($seoSettings?->seo_keywords)<meta name="keywords" content="{{ $seoSettings->seo_keywords }}">@endif
    <meta name="robots" content="{{ ($seoSettings?->seo_indexing_enabled ?? true) ? 'index,follow' : 'noindex,nofollow' }}">
    <link rel="canonical" href="{{ $seoCanonical }}">
    <meta property="og:type" content="website"><meta property="og:site_name" content="{{ $seoSiteName }}"><meta property="og:title" content="{{ $seoTitle }}"><meta property="og:description" content="{{ $seoDescription }}"><meta property="og:url" content="{{ $seoCanonical }}">
    @if($seoSettings?->seo_og_image)<meta property="og:image" content="{{ $seoSettings->seo_og_image }}"><meta name="twitter:card" content="summary_large_image">@else<meta name="twitter:card" content="summary">@endif
    @if(request()->routeIs('home'))<script type="application/ld+json">{!! json_encode(['@context'=>'https://schema.org','@type'=>$seoSettings?->seo_business_type ?: 'LocalBusiness','name'=>$seoSiteName,'url'=>$seoCanonical,'description'=>$seoDescription,'telephone'=>$seoSettings?->phone,'email'=>$seoSettings?->email,'address'=>$seoSettings?->address,'sameAs'=>array_values(array_filter([$seoSettings?->instagram_url,$seoSettings?->facebook_url]))], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) !!}</script>@endif
    @vite(['resources/css/app.css','resources/js/app.js'])
</head>
<body class="bg-stone-50 text-stone-900">
    @php($publicModules = app(App\Support\ModuleManager::class))
    @php($publicSettings = App\Models\SiteSetting::query()->first())
    @php($publicReservationsEnabled = $publicModules->enabled('reservations') && ($publicSettings?->reservations_enabled ?? false))
    <header class="sticky top-0 z-40 border-b border-stone-200 bg-white/95 text-stone-900 shadow-sm backdrop-blur">
        <div class="mx-auto flex max-w-7xl items-center justify-between px-4 py-4 sm:px-6">
            <a href="{{ route('home') }}#inicio" data-analytics-event="nav_inicio" class="text-xl font-black text-stone-950">{{ config('brand.name') }}</a>
            <nav class="hidden items-center gap-5 text-sm font-semibold text-stone-800 md:flex">
                @if($publicModules->enabled('inventory'))<a data-analytics-event="nav_productos" class="hover:text-orange-700" href="{{ route('public.products') }}">Productos</a>@endif
                <a data-analytics-event="nav_horarios" class="hover:text-orange-700" href="{{ route('home') }}#horarios">Horarios</a>
                <a data-analytics-event="nav_contacto" class="hover:text-orange-700" href="{{ route('home') }}#contacto">Contacto</a>
                <a data-public-reservation-link data-analytics-event="nav_reservar" href="{{ route('home') }}#reservar" class="rounded-full bg-orange-700 px-4 py-2 text-white {{ $publicReservationsEnabled ? '' : 'hidden' }}">Reservar</a>
                <a href="{{ route('login') }}" class="text-orange-700">Iniciar sesión</a>
            </nav>
            <button type="button" data-app-menu-open class="rounded-xl border border-stone-300 bg-white px-3 py-2 font-bold md:hidden" aria-label="Abrir navegación" aria-haspopup="dialog">☰ Menú</button>
        </div>
    </header>
    <dialog data-app-menu class="m-0 ml-auto h-dvh max-h-none w-[min(88vw,24rem)] max-w-none bg-white p-0 text-stone-900 shadow-2xl backdrop:bg-black/65">
        <div class="flex min-h-full flex-col"><div class="flex items-center justify-between border-b border-stone-200 p-4"><strong>{{ config('brand.name') }}</strong><button type="button" data-app-menu-close class="rounded-xl border border-stone-300 px-3 py-2 font-bold">✕ Cerrar</button></div><nav class="grid gap-2 p-4 text-base font-bold"> <a data-app-menu-close data-analytics-event="nav_inicio_mobile" class="rounded-xl px-4 py-3 hover:bg-stone-100" href="{{ route('home') }}#inicio">Inicio</a>@if($publicModules->enabled('inventory'))<a data-app-menu-close data-analytics-event="nav_productos_mobile" class="rounded-xl px-4 py-3 hover:bg-stone-100" href="{{ route('public.products') }}">Productos</a>@endif<a data-app-menu-close data-analytics-event="nav_horarios_mobile" class="rounded-xl px-4 py-3 hover:bg-stone-100" href="{{ route('home') }}#horarios">Horarios</a><a data-app-menu-close data-analytics-event="nav_contacto_mobile" class="rounded-xl px-4 py-3 hover:bg-stone-100" href="{{ route('home') }}#contacto">Contacto</a><a data-public-reservation-link data-app-menu-close data-analytics-event="nav_reservar_mobile" class="rounded-xl bg-orange-700 px-4 py-3 text-white {{ $publicReservationsEnabled ? '' : 'hidden' }}" href="{{ route('home') }}#reservar">Reservar</a><a class="rounded-xl px-4 py-3 text-orange-700 hover:bg-orange-50" href="{{ route('login') }}">Iniciar sesión</a></nav></div>
    </dialog>
    <main>{{ $slot ?? '' }}@yield('content')</main>
    <footer class="border-t border-stone-200 bg-stone-950 text-stone-300">
        <div class="mx-auto flex max-w-7xl flex-col justify-between gap-4 px-4 py-8 text-sm sm:flex-row sm:items-center sm:px-6">
            <p>© {{ now()->year }} {{ config('brand.name') }}</p>
            <div class="flex flex-wrap gap-x-5 gap-y-2">@if($publicSettings?->privacy_policy)<a href="{{ route('public.privacy') }}" class="hover:text-white">Privacidad</a>@endif @if($publicSettings?->legal_notice)<a href="{{ route('public.legal-notice') }}" class="hover:text-white">Aviso legal</a>@endif<a href="{{ route('login') }}" class="font-semibold text-white">Acceso del equipo</a></div>
        </div>
    </footer>
    <button type="button" data-scroll-top aria-label="Volver arriba" title="Volver arriba" class="fixed bottom-5 right-5 z-30 hidden size-12 rounded-full bg-orange-700 text-xl font-bold text-white shadow-lg transition hover:-translate-y-1 hover:bg-orange-600">↑</button>
</body>
</html>
