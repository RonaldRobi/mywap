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

        @if (request()->routeIs('login', 'register'))
            @php
                $appName = config('app.name', 'myWAP');
                $rawOgImage = $page['props']['brand']['og_image_path']
                    ?? $page['props']['brand']['system_logo_path']
                    ?? asset('images/og-login.png');

                // WhatsApp/Facebook require an ABSOLUTE https URL for og:image.
                // Stored paths come through as relative "/storage/..." so promote them
                // to a full URL rooted at the current host.
                $ogImage = \Illuminate\Support\Str::startsWith($rawOgImage, ['http://', 'https://'])
                    ? $rawOgImage
                    : url($rawOgImage);

                if (request()->routeIs('register')) {
                    $ogTitle = 'Daftar Akaun Baru - '.$appName;
                    $ogDescription = 'Daftar sebagai ahli PKPIM, ABIM atau WADAH. Sistem tetapkan organisasi anda secara automatik ikut umur.';
                } else {
                    $ogTitle = 'Log Masuk - '.$appName;
                    $ogDescription = 'Log masuk ke akaun '.$appName.' anda.';
                }
            @endphp
            <meta property="og:title" content="{{ $ogTitle }}" />
            <meta property="og:description" content="{{ $ogDescription }}" />
            <meta property="og:image" content="{{ $ogImage }}" />
            <meta property="og:image:secure_url" content="{{ $ogImage }}" />
            <meta property="og:image:width" content="1200" />
            <meta property="og:image:height" content="630" />
            <meta property="og:url" content="{{ url()->current() }}" />
            <meta property="og:type" content="website" />
            <meta property="og:site_name" content="{{ $appName }}" />
            <meta name="description" content="{{ $ogDescription }}" />
            <meta name="twitter:card" content="summary_large_image" />
            <meta name="twitter:title" content="{{ $ogTitle }}" />
            <meta name="twitter:description" content="{{ $ogDescription }}" />
            <meta name="twitter:image" content="{{ $ogImage }}" />
        @endif

        @if (request()->routeIs('events.register.public', 'forms.public'))
            @php
                $appName = config('app.name', 'myWAP');
                $formProps = $page['props']['form'] ?? [];
                $ogTitle = $formProps['title'] ?? ($page['props']['event']['title'] ?? $appName);
                $ogDescription = $formProps['description'] ?? 'Sila isi borang pendaftaran ini.';
                $rawOgImage = $formProps['header_image_url']
                    ?? $page['props']['event']['featured_image_url']
                    ?? $page['props']['brand']['og_image_path']
                    ?? $page['props']['brand']['system_logo_path']
                    ?? asset('images/og-login.png');
                $ogImage = \Illuminate\Support\Str::startsWith($rawOgImage, ['http://', 'https://'])
                    ? $rawOgImage
                    : url($rawOgImage);
            @endphp
            <meta property="og:title" content="{{ $ogTitle }}" />
            <meta property="og:description" content="{{ \Illuminate\Support\Str::limit(strip_tags((string) $ogDescription), 200) }}" />
            <meta property="og:image" content="{{ $ogImage }}" />
            <meta property="og:image:secure_url" content="{{ $ogImage }}" />
            <meta property="og:url" content="{{ url()->current() }}" />
            <meta property="og:type" content="website" />
            <meta property="og:site_name" content="{{ $appName }}" />
            <meta name="description" content="{{ \Illuminate\Support\Str::limit(strip_tags((string) $ogDescription), 200) }}" />
            <meta name="twitter:card" content="summary_large_image" />
            <meta name="twitter:title" content="{{ $ogTitle }}" />
            <meta name="twitter:description" content="{{ \Illuminate\Support\Str::limit(strip_tags((string) $ogDescription), 200) }}" />
            <meta name="twitter:image" content="{{ $ogImage }}" />
        @endif
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>

