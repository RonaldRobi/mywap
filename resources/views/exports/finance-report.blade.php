<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Laporan Kewangan {{ $year }}</title>
    <style>
        body { font-family: sans-serif; font-size: 11px; color: #333; }
        .cover { text-align: center; padding: 80px 40px 40px; }
        .cover h1 { font-size: 24px; margin-bottom: 8px; }
        .cover h2 { font-size: 16px; color: #555; margin-top: 0; }
        .cover .meta { margin-top: 60px; font-size: 13px; color: #666; }
        .cover .meta p { margin: 4px 0; }
        .page-break { page-break-before: always; }
        h2 { font-size: 14px; border-bottom: 2px solid #333; padding-bottom: 4px; margin-top: 24px; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th, td { border: 1px solid #ccc; padding: 6px 8px; text-align: left; font-size: 10px; }
        th { background: #f5f5f5; font-size: 9px; text-transform: uppercase; }
        td.right { text-align: right; }
        .summary { margin-top: 16px; }
        .summary table { width: auto; }
        .summary td { border: none; padding: 3px 8px; font-size: 11px; }
        .summary td:last-child { font-weight: bold; }
        .signature { margin-top: 60px; }
        .signature table { border: none; }
        .signature td { border: none; padding: 12px 20px; text-align: center; width: 50%; font-size: 11px; }
        .signature .line { border-bottom: 1px solid #333; margin-bottom: 4px; }
        .footer { margin-top: 40px; padding-top: 8px; border-top: 1px solid #ccc; font-size: 9px; color: #999; text-align: center; }
    </style>
</head>
<body>
    <!-- Cover -->
    <div class="cover">
        <h1>LAPORAN KEWANGAN</h1>
        <h2>{{ $org ? $org->name : 'Semua Organisasi' }} · Tahun {{ $year }}</h2>
        <div class="meta">
            <p>Dijana oleh: {{ $generatedBy }}</p>
            <p>Tarikh: {{ $generatedAt->format('d/m/Y h:i A') }}</p>
            <p>Jumlah Transaksi: {{ $transactions->count() }}</p>
        </div>
    </div>

    <div class="page-break"></div>

    <!-- Summary -->
    <h2>Ringkasan Pendapatan</h2>
    <div class="summary">
        <table>
            <tr><td>Jumlah Pendapatan</td><td>RM {{ number_format($totalRevenue, 2) }}</td></tr>
            @foreach($bySource as $s)
            <tr><td>{{ $s['type'] }}</td><td>RM {{ number_format($s['total'], 2) }}</td></tr>
            @endforeach
        </table>
    </div>

    <!-- Transaction Table -->
    <h2>Senarai Transaksi</h2>
    <table>
        <thead>
            <tr><th>#</th><th>Tarikh</th><th>Ahli</th><th>No Ahli</th><th>Jenis</th><th>Jumlah (RM)</th><th>Rujukan</th></tr>
        </thead>
        <tbody>
            @foreach($transactions as $i => $p)
            <tr>
                <td>{{ $i + 1 }}</td>
                <td>{{ \Carbon\Carbon::parse($p['created_at'])->format('d/m/Y') ?? '—' }}</td>
                <td>{{ $p['member_name'] }}</td>
                <td>{{ $p['member_no'] }}</td>
                <td>{{ $p['type'] }}</td>
                <td class="right">{{ number_format((float) $p['amount'], 2) }}</td>
                <td>{{ $p['reference'] }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <!-- Signature -->
    <div class="signature">
        <table>
            <tr>
                <td>
                    <p>&nbsp;</p>
                    <div class="line"></div>
                    <p>Disediakan oleh,</p>
                    <p style="font-weight:bold;">{{ $generatedBy }}</p>
                </td>
                <td>
                    <p>&nbsp;</p>
                    <div class="line"></div>
                    <p>Disahkan oleh,</p>
                    <p style="font-weight:bold;">Pegawai Kewangan</p>
                    <p style="font-size:10px; color:#999;">{{ $org ? $org->name : '' }}</p>
                </td>
            </tr>
        </table>
    </div>

    <div class="footer">
        Laporan ini dijana secara automatik oleh sistem MyMarhalah pada {{ $generatedAt->format('d/m/Y h:i A') }}.
    </div>
</body>
</html>
