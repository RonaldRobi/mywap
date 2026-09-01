@php
    $brandName = config('app.name', 'myWAP');
    $brandLogo = null;
    $reportEmail = 'admin@mywap.my';
    $reportPhone = null;

    try {
        $setting = cache('app_settings');
        if ($setting) {
            $brandName = $setting->app_name ?: $brandName;
            $brandLogo = $setting->system_logo_path ?: null;
            $reportEmail = $setting->admin_contact_email ?: $reportEmail;
            $reportPhone = $setting->admin_contact_phone ?: null;
        }
    } catch (Throwable) {
        // DB mungkin bermasalah — guna nilai default.
    }

    if ($brandLogo && ! \Illuminate\Support\Str::startsWith($brandLogo, ['http://', 'https://'])) {
        $brandLogo = url($brandLogo);
    }
@endphp
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('code') | {{ $brandName }}</title>
    <meta name="robots" content="noindex, nofollow">
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
            background: #0f172a;
            color: #e2e8f0;
            display: grid;
            place-items: center;
            padding: 24px 16px;
        }
        .card {
            width: min(480px, 100%);
            text-align: center;
            padding: 48px 32px;
            border-radius: 20px;
            background: #111827;
            border: 1px solid rgba(255, 255, 255, 0.1);
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.35);
        }
        .logo {
            height: 56px;
            max-width: 200px;
            object-fit: contain;
            margin-bottom: 24px;
        }
        .logo-text {
            font-size: 24px;
            font-weight: 800;
            letter-spacing: -0.02em;
            margin-bottom: 24px;
            color: #f8fafc;
        }
        .code {
            font-size: 72px;
            font-weight: 800;
            line-height: 1;
            background: linear-gradient(135deg, #34d399, #22d3ee);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
            margin-bottom: 16px;
        }
        h1 {
            margin: 0 0 10px;
            font-size: 20px;
            font-weight: 700;
            color: #f8fafc;
        }
        p {
            margin: 0 0 8px;
            font-size: 15px;
            line-height: 1.6;
            color: #94a3b8;
        }
        .actions {
            margin-top: 28px;
            display: flex;
            flex-direction: column;
            gap: 10px;
            align-items: center;
        }
        .btn {
            display: inline-block;
            min-width: 220px;
            padding: 12px 24px;
            border-radius: 12px;
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
            font-family: inherit;
            border: none;
            cursor: pointer;
            text-align: center;
            transition: opacity .15s ease, transform .15s ease;
        }
        .btn:active { transform: translateY(1px); }
        .btn:hover { opacity: .9; }
        .btn-primary {
            background: linear-gradient(135deg, #10b981, #06b6d4);
            color: #fff;
        }
        .btn-secondary {
            background: transparent;
            color: #cbd5e1;
            border: 1px solid rgba(255, 255, 255, 0.18);
        }
        .report {
            margin-top: 20px;
            font-size: 13px;
            color: #64748b;
        }
        .report a {
            color: #34d399;
            text-decoration: none;
            font-weight: 600;
        }
        .report a:hover { text-decoration: underline; }
        .phone {
            display: block;
            margin-top: 4px;
            color: #64748b;
        }
        .phone a { color: #34d399; text-decoration: none; font-weight: 600; }
        .phone a:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="card">
        @if ($brandLogo)
            <img src="{{ $brandLogo }}" alt="{{ $brandName }}" class="logo">
        @else
            <div class="logo-text">{{ $brandName }}</div>
        @endif

        <div class="code">@yield('code')</div>
        <h1>@yield('title')</h1>
        @yield('message')

        <div class="actions">
            <button type="button" class="btn btn-primary" onclick="goBack()">&larr; Kembali</button>
            <a href="{{ url('/') }}" class="btn btn-secondary">Laman Utama</a>
        </div>

        @hasSection('report')
            <div class="report">
                @php
                    $reportCode = trim(strip_tags($__env->yieldContent('code')));
                    $reportSubject = 'Laporan Masalah — ' . ($reportCode ?: 'Ralat');
                @endphp
                Sekiranya masalah berterusan, sila <a href="mailto:{{ $reportEmail }}?subject={{ rawurlencode($reportSubject) }}">laporkan kepada pentadbir</a>.
                @if ($reportPhone)
                    <span class="phone">atau hubungi <a href="tel:{{ preg_replace('/[^0-9+]/', '', $reportPhone) }}">{{ $reportPhone }}</a></span>
                @endif
            </div>
        @endif
    </div>

    <script>
        function goBack() {
            if (document.referrer && document.referrer !== location.href) {
                location.href = document.referrer;
            } else if (window.history.length > 1) {
                window.history.back();
            } else {
                location.href = '/';
            }
        }
    </script>
</body>
</html>
