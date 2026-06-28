<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <meta name="theme-color" content="#111827">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
        <meta name="apple-mobile-web-app-title" content="{{ $page['props']['brand']['app_name'] ?? config('app.name', 'myWAP') }}">

        <title inertia>{{ config('app.name', 'Laravel') }}</title>

        <!-- PWA Manifest & Icons -->
        <link rel="manifest" href="/manifest.json">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">
        <link rel="icon" type="image/png" sizes="192x192" href="/pwa-icon-192.png">

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @routes
        @vite(['resources/js/app.js', "resources/js/Pages/{$page['component']}.vue"])
        @inertiaHead

        @if (request()->routeIs('login'))
            @php
                $ogImage = $page['props']['brand']['og_image_path'] ?? $page['props']['brand']['system_logo_path'] ?? asset('images/og-login.png');
            @endphp
            <meta property="og:title" content="Login - {{ config('app.name') }}" />
            <meta property="og:description" content="Log masuk ke akaun myWAP anda" />
            <meta property="og:image" content="{{ $ogImage }}" />
            <meta property="og:url" content="{{ url()->current() }}" />
            <meta property="og:type" content="website" />
            <meta property="og:site_name" content="{{ config('app.name') }}" />
            <meta name="twitter:card" content="summary_large_image" />
            <meta name="twitter:title" content="Login - {{ config('app.name') }}" />
            <meta name="twitter:description" content="Log masuk ke akaun myWAP anda" />
            <meta name="twitter:image" content="{{ $ogImage }}" />
        @endif
    </head>
    <body class="font-sans antialiased">
        @inertia

        <!-- Service Worker Registration -->
        <script>
            if ('serviceWorker' in navigator) {
                window.addEventListener('load', () => {
                    navigator.serviceWorker.register('/sw.js').catch(() => {});
                });
            }
        </script>
    </body>
</html>

