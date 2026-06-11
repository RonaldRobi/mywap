<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $metaTitle }}</title>

    <meta name="description" content="{{ $metaDescription }}">

    <meta property="og:type" content="{{ $metaType }}">
    <meta property="og:title" content="{{ $metaTitle }}">
    <meta property="og:description" content="{{ $metaDescription }}">
    <meta property="og:image" content="{{ $metaImage }}">
    <meta property="og:url" content="{{ $metaUrl }}">
    <meta property="og:site_name" content="myWAP">

    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $metaTitle }}">
    <meta name="twitter:description" content="{{ $metaDescription }}">
    <meta name="twitter:image" content="{{ $metaImage }}">

    <meta http-equiv="refresh" content="2;url={{ $redirectUrl }}">

    <style>
        body {
            margin: 0;
            min-height: 100vh;
            font-family: system-ui, -apple-system, Segoe UI, Roboto, sans-serif;
            display: grid;
            place-items: center;
            background: #0f172a;
            color: #fff;
        }
        .card {
            width: min(560px, calc(100% - 32px));
            border-radius: 18px;
            overflow: hidden;
            background: #111827;
            border: 1px solid rgba(255, 255, 255, 0.14);
        }
        .card img {
            width: 100%;
            aspect-ratio: 16 / 9;
            object-fit: cover;
            display: block;
        }
        .content {
            padding: 16px;
        }
        h1 {
            margin: 0 0 8px;
            font-size: 20px;
            line-height: 1.3;
        }
        p {
            margin: 0 0 14px;
            color: #d1d5db;
            font-size: 14px;
        }
        a {
            color: #86efac;
            text-decoration: none;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="card">
        <img src="{{ $metaImage }}" alt="{{ $metaTitle }}">
        <div class="content">
            <h1>{{ $metaTitle }}</h1>
            <p>{{ $metaDescription }}</p>
            <a href="{{ $redirectUrl }}">Buka di myWAP</a>
        </div>
    </div>

    <script>
        setTimeout(function () {
            window.location.href = @json($redirectUrl);
        }, 1200);
    </script>
</body>
</html>
