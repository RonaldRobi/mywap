<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="utf-8">
    <title>Mengalihkan ke Pembayaran...</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background:#f8fafc; display:flex; align-items:center; justify-content:center; min-height:100vh; margin:0; color:#334155; }
        .box { text-align:center; }
        .box h1 { font-size:18px; margin:0 0 6px; }
        .box p { font-size:14px; color:#64748b; margin:0 0 16px; }
        .box a { display:inline-block; background:#6366f1; color:#fff; text-decoration:none; font-weight:600; padding:12px 24px; border-radius:10px; font-size:14px; }
    </style>
</head>
<body>
    <div class="box">
        <h1>Mengalihkan anda ke senangPay...</h1>
        <p>Jika anda tidak dialihkan secara automatik, klik butang di bawah.</p>
        <a href="{{ $url }}" onclick="event.preventDefault(); document.getElementById('sp-pay').submit();">Teruskan ke Pembayaran</a>
    </div>

    <form id="sp-pay" method="POST" action="{{ $url }}" style="display:none;">
        @foreach($fields as $name => $value)
            <input type="hidden" name="{{ $name }}" value="{{ $value }}">
        @endforeach
    </form>

    <script>
        document.getElementById('sp-pay').submit();
    </script>
</body>
</html>
