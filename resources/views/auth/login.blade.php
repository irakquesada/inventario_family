<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Iniciar sesión · {{ config('brand.name') }}</title>
    <script>
        (() => {
            const preference = localStorage.getItem('theme') || 'system';
            const dark = preference === 'dark' || (preference === 'system' && matchMedia('(prefers-color-scheme: dark)').matches);
            document.documentElement.classList.toggle('dark', dark);
            document.documentElement.dataset.theme = preference;
        })();
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @if (config('services.turnstile.enabled'))
        <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
    @endif
</head>
<body class="grid min-h-screen place-items-center bg-stone-100 px-4">
    <button type="button" data-theme-toggle class="absolute right-4 top-4 rounded-lg border border-stone-300 bg-white px-3 py-2 text-sm font-semibold" aria-label="Cambiar tema">Tema</button>
    <main class="w-full max-w-md rounded-2xl border border-stone-200 bg-white p-6 shadow-sm sm:p-8">
        <p class="text-sm font-semibold uppercase tracking-wider text-orange-700">{{ config('brand.short_name') }}</p>
        <h1 class="mt-2 text-3xl font-bold">Iniciar sesión</h1>
        <p class="mt-2 text-stone-600">Accede al panel de gestión.</p>
        <form method="POST" action="{{ route('login.store') }}" class="mt-8 space-y-5">@csrf
            <label class="block"><span class="mb-2 block font-medium">Correo electrónico</span>
                <input name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="email" class="w-full rounded-xl border border-stone-300 px-4 py-3 focus:border-orange-600 focus:outline-none focus:ring-2 focus:ring-orange-200">
            </label>
            <label class="block"><span class="mb-2 block font-medium">Contraseña</span>
                <span class="relative block">
                    <input name="password" type="password" required autocomplete="current-password" data-password-input class="w-full rounded-xl border border-stone-300 px-4 py-3 pr-24 focus:border-orange-600 focus:outline-none focus:ring-2 focus:ring-orange-200">
                    <button type="button" data-password-toggle aria-pressed="false" class="absolute inset-y-1 right-1 rounded-lg px-3 text-sm font-semibold text-stone-600 hover:bg-stone-100">Mostrar</button>
                </span>
            </label>
            <label class="flex items-center gap-2 text-sm"><input name="remember" type="checkbox" value="1"> Mantener la sesión</label>
            @if (config('services.turnstile.enabled'))
                <div class="cf-turnstile" data-sitekey="{{ config('services.turnstile.site_key') }}" data-theme="auto" data-language="es" data-appearance="interaction-only"></div>
                <noscript><p class="rounded-lg bg-amber-50 p-3 text-sm text-amber-900">Activa JavaScript para completar la comprobación de seguridad.</p></noscript>
            @endif
            @if ($errors->any())<p class="rounded-lg bg-red-50 p-3 text-sm text-red-700">{{ $errors->first() }}</p>@endif
            <button class="w-full rounded-xl bg-orange-700 px-4 py-3 font-semibold text-white hover:bg-orange-800">Entrar</button>
        </form>
    </main>
</body>
</html>
