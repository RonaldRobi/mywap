<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        body { font-family: sans-serif; font-size: 11px; }
        h1 { text-align: center; margin-bottom: 4px; font-size: 16px; }
        h2 { text-align: center; margin-top: 0; font-size: 12px; color: #555; }
        table { width: 100%; border-collapse: collapse; margin-top: 16px; }
        th, td { border: 1px solid #ccc; padding: 6px 8px; text-align: left; }
        th { background: #f5f5f5; font-size: 10px; text-transform: uppercase; }
        .hadir { color: #16a34a; font-weight: bold; }
        .tidak { color: #d97706; font-weight: bold; }
        .footer { margin-top: 20px; font-size: 10px; color: #666; }
    </style>
</head>
<body>
    <h1>{{ $title }}</h1>
    <h2>Dijana oleh {{ $generatedBy }} — {{ $generatedAt->format('d/m/Y H:i') }}</h2>

    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Nama</th>
                <th>No Ahli</th>
                <th>Organisasi</th>
                <th>Event</th>
                <th>Telefon</th>
                <th>Bayaran</th>
                <th>Kehadiran</th>
                <th>Masa Hadir</th>
            </tr>
        </thead>
        <tbody>
            @foreach($rows as $i => $r)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>{{ $r['name'] }}</td>
                    <td>{{ $r['member_no'] ?? '—' }}</td>
                    <td>{{ $r['organization_name'] ?? '—' }}</td>
                    <td>{{ $r['event_title'] ?? '—' }}</td>
                    <td>{{ $r['phone'] ?? '—' }}</td>
                    <td>{{ match($r['payment_status'] ?? 'paid') { 'successful' => 'Berjaya', 'pending' => 'Menunggu', 'failed' => 'Gagal', 'refunded' => 'Dipulangkan', default => '—' } }}</td>
                    <td class="{{ $r['attended'] ? 'hadir' : 'tidak' }}">{{ $r['attended'] ? 'Hadir' : 'Tidak Hadir' }}</td>
                    <td>{{ $r['attended_at'] ?? '—' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    @php
        $total = count($rows);
        $hadir = collect($rows)->where('attended', true)->count();
    @endphp

    <div class="summary">
        <p><strong>Jumlah Pendaftaran:</strong> {{ $total }}</p>
        <p><strong>Hadir:</strong> {{ $hadir }}</p>
        <p><strong>Tidak Hadir:</strong> {{ $total - $hadir }}</p>
    </div>
</body>
</html>
