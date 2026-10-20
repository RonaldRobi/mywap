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
        .notice { margin: 8px 0 0; padding: 6px 8px; background: #fff7ed; border: 1px solid #fed7aa; color: #9a3412; font-size: 10px; }
    </style>
</head>
<body>
    <h1>{{ $title }}</h1>
    <h2>Dijana oleh {{ $generatedBy }} — {{ $generatedAt->format('d/m/Y H:i') }}</h2>

    @if(isset($shown, $total) && $shown < $total)
        <p class="notice">
            Menunjukkan {{ $shown }} daripada {{ $total }} rekod. Gunakan <strong>Export Excel</strong>
            untuk laporan penuh.
        </p>
    @endif

    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Nama</th>
                <th>No Ahli</th>
                <th>Organisasi myWAP</th>
                <th>Event</th>
                <th>Telefon</th>
                <th>Bayaran</th>
                <th>Kehadiran</th>
                <th>Masa Hadir</th>
                @foreach($formColumns as $column)
                    <th>{{ $column['label'] }}</th>
                @endforeach
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
                    @foreach($r['form_values'] as $value)
                        <td>{{ $value !== null && $value !== '' ? $value : '—' }}</td>
                    @endforeach
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
