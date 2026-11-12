<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="utf-8">
    <title>Laporan Program</title>
    <style>
        body { font-family: sans-serif; font-size: 10px; color: #111827; }
        h1 { text-align: center; margin: 0 0 2px; font-size: 16px; }
        .meta { text-align: center; margin: 0 0 12px; font-size: 10px; color: #4b5563; }
        .stats { width: 100%; border-collapse: separate; border-spacing: 6px; margin: 0 0 12px; }
        .stats td { width: 20%; border: 1px solid #e5e7eb; background: #f9fafb; padding: 8px; vertical-align: top; }
        .stats .value { font-size: 16px; font-weight: bold; color: #111827; }
        .stats .label { margin-top: 2px; font-size: 9px; text-transform: uppercase; letter-spacing: 0.04em; color: #6b7280; }
        .section-title { margin: 14px 0 6px; font-size: 11px; text-transform: uppercase; letter-spacing: 0.05em; color: #374151; }
        table.list { width: 100%; border-collapse: collapse; }
        table.list th, table.list td { border: 1px solid #d1d5db; padding: 5px 7px; text-align: left; vertical-align: top; }
        table.list th { background: #f3f4f6; font-size: 9px; text-transform: uppercase; letter-spacing: 0.04em; }
        table.list td.number { text-align: right; white-space: nowrap; }
        .muted { color: #6b7280; }
        .notice { margin: 0 0 10px; padding: 6px 8px; background: #fff7ed; border: 1px solid #fed7aa; color: #9a3412; font-size: 9px; }
        .footer { margin-top: 14px; font-size: 9px; color: #6b7280; }
    </style>
</head>
<body>
    <h1>Laporan Program</h1>
    <p class="meta">
        Tempoh: {{ $periodLabel }} | Skop: {{ $scopeLabel }}
        | Dijana oleh {{ $generatedBy }} pada {{ $generatedAt->format('d/m/Y H:i') }}
    </p>

    <table class="stats">
        <tr>
            @foreach($summary as $row)
                <td>
                    <div class="value">{{ $row['value'] }}</div>
                    <div class="label">{{ $row['label'] }}</div>
                </td>
            @endforeach
            <td>
                <div class="value">{{ $attendanceRate }}%</div>
                <div class="label">Kadar kehadiran</div>
            </td>
        </tr>
    </table>

    @if($categories->isNotEmpty())
        <h2 class="section-title">Program mengikut kategori</h2>
        <p class="muted" style="margin:0 0 8px;">
            @foreach($categories as $category)
                {{ $category['label'] }}: {{ $category['total'] }}@if(! $loop->last) | @endif
            @endforeach
        </p>
    @endif

    @if($shown < $total)
        <p class="notice">
            Menunjukkan {{ $shown }} daripada {{ $total }} program. Muat turun CSV untuk senarai penuh.
        </p>
    @endif

    <h2 class="section-title">Senarai program</h2>

    @if($events->isEmpty())
        <p class="muted">Tiada program yang telah selesai untuk tempoh ini.</p>
    @else
        <table class="list">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Program</th>
                    <th>Kategori</th>
                    <th>Organisasi</th>
                    <th>Cawangan</th>
                    <th>Tarikh</th>
                    <th>Lokasi</th>
                    <th>Peserta unik</th>
                    <th>Hadir</th>
                    <th>Kadar</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @foreach($events as $index => $event)
                    <tr>
                        <td>{{ $index + 1 }}</td>
                        <td>{{ $event['title'] }}</td>
                        <td>{{ $event['category_label'] }}</td>
                        <td>{{ $event['organization_name'] }}</td>
                        <td>{{ $event['branch_name'] ?? 'Semua cawangan' }}</td>
                        <td>{{ $event['date_label'] }}</td>
                        <td>{{ $event['location_or_link'] ?? '-' }}</td>
                        <td class="number">{{ $event['unique_participants_count'] }}</td>
                        <td class="number">{{ $event['attended_count'] }}</td>
                        <td class="number">{{ $event['attendance_rate'] }}%</td>
                        <td>{{ $event['status_label'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <p class="footer">myWAP - Laporan Program</p>
</body>
</html>
