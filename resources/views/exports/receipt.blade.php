<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Resit Yuran {{ $member->name }} {{ $fee->year }}</title>
    <style>
        body { font-family: sans-serif; font-size: 12px; color: #333; }
        .header { text-align: center; margin-bottom: 24px; border-bottom: 2px solid #333; padding-bottom: 12px; }
        .header h1 { font-size: 18px; margin: 0 0 4px; }
        .header p { font-size: 13px; color: #666; margin: 0; }
        .meta { margin-bottom: 20px; }
        .meta table { width: 100%; }
        .meta td { padding: 3px 6px; }
        .meta td:first-child { font-weight: bold; width: 140px; color: #555; }
        .details { border-collapse: collapse; width: 100%; margin-bottom: 24px; }
        .details th, .details td { border: 1px solid #ccc; padding: 8px 10px; text-align: left; }
        .details th { background: #f5f5f5; font-size: 11px; text-transform: uppercase; }
        .total { font-size: 14px; font-weight: bold; text-align: right; margin-top: 8px; }
        .footer { margin-top: 32px; padding-top: 12px; border-top: 1px solid #ccc; font-size: 10px; color: #999; text-align: center; }
    </style>
</head>
<body>
    <div class="header">
        <h1>RESIT PEMBAYARAN YURAN</h1>
        <p>MyMarhalah — Sistem Pengurusan Keahlian</p>
    </div>

    <div class="meta">
        <table>
            <tr><td>No Resit</td><td>: {{ $payment->reference }}</td></tr>
            <tr><td>Tarikh Bayaran</td><td>: {{ $payment->created_at->format('d/m/Y h:i A') }}</td></tr>
            <tr><td>Nama Ahli</td><td>: {{ $member->name }}</td></tr>
            <tr><td>No Ahli</td><td>: {{ $member->member_no ?? '—' }}</td></tr>
            <tr><td>No IC</td><td>: {{ $member->ic_number ?? '—' }}</td></tr>
            <tr><td>Organisasi</td><td>: {{ $member->organization?->name ?? '—' }}</td></tr>
            <tr><td>Tahun Yuran</td><td>: {{ $fee->year }}</td></tr>
        </table>
    </div>

    <table class="details">
        <thead>
            <tr><th>Perkara</th><th>Jumlah (RM)</th></tr>
        </thead>
        <tbody>
            <tr>
                <td>Yuran Keahlian {{ $member->organization?->name ?? '' }} {{ $fee->year }}</td>
                <td style="text-align: right;">{{ number_format((float) $payment->amount, 2) }}</td>
            </tr>
        </tbody>
    </table>

    <div class="total">Jumlah Dibayar: RM {{ number_format((float) $payment->amount, 2) }}</div>

    <div class="footer">
        Resit ini dijana secara automatik oleh sistem MyMarhalah.<br>
        {{ now()->format('d/m/Y h:i A') }}
    </div>
</body>
</html>
