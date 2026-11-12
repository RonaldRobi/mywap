<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        body { font-family: sans-serif; font-size: 10px; color: #1f2937; }
        h1 { text-align: center; margin: 0 0 4px; font-size: 16px; }
        h2 { text-align: center; margin: 0 0 14px; font-size: 11px; color: #4b5563; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #d1d5db; padding: 5px 6px; text-align: left; vertical-align: top; }
        th { background: #f3f4f6; font-size: 9px; text-transform: uppercase; letter-spacing: 0.04em; }
        .notice { margin: 0 0 10px; padding: 7px 9px; background: #fffbeb; border: 1px solid #fde68a; color: #92400e; }
        .member { color: #0369a1; font-weight: bold; }
        .prospect { color: #b45309; font-weight: bold; }
        .summary { margin-top: 14px; }
    </style>
</head>
<body>
    <h1>{{ $title }}</h1>
    <h2>Dijana oleh {{ $generatedBy }} pada {{ $generatedAt->format('d/m/Y H:i') }}</h2>

    @if($shown < $total)
        <p class="notice">
            Menunjukkan {{ $shown }} daripada {{ $total }} rekod. Gunakan Export Excel untuk laporan penuh.
        </p>
    @endif

    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Nama</th>
                <th>Emel</th>
                <th>No. Telefon</th>
                <th>No. IC</th>
                <th>Organisasi</th>
                <th>Program</th>
                <th>Status</th>
                <th>No. Ahli</th>
                <th>Sumber</th>
                <th>Tarikh Dicipta</th>
                <th>Tarikh Dijemput</th>
                <th>Nota</th>
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $row)
                <tr>
                    @foreach($row as $index => $value)
                        @if($index === 7)
                            <td class="{{ $value === 'Sudah jadi ahli' ? 'member' : 'prospect' }}">{{ $value }}</td>
                        @else
                            <td>{{ $value }}</td>
                        @endif
                    @endforeach
                </tr>
            @empty
                <tr>
                    <td colspan="13" style="text-align:center;padding:18px;color:#6b7280;">Tiada rekod untuk tapisan ini.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="summary">
        <p><strong>Jumlah rekod:</strong> {{ $shown }}@if($shown < $total) daripada {{ $total }}@endif</p>
    </div>
</body>
</html>
