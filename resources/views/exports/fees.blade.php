<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Laporan Yuran {{ $year }}</title>
    <style>
        body { font-family: sans-serif; font-size: 11px; }
        h1 { text-align: center; margin-bottom: 4px; font-size: 16px; }
        h2 { text-align: center; margin-top: 0; font-size: 13px; color: #555; }
        table { width: 100%; border-collapse: collapse; margin-top: 16px; }
        th, td { border: 1px solid #ccc; padding: 6px 8px; text-align: left; }
        th { background: #f5f5f5; font-size: 10px; text-transform: uppercase; }
        .badge-paid { color: #16a34a; font-weight: bold; }
        .badge-due { color: #d97706; font-weight: bold; }
        .summary { margin-top: 20px; font-size: 12px; }
        .summary p { margin: 2px 0; }
    </style>
</head>
<body>
    <h1>Laporan Yuran Keahlian</h1>
    <h2>Tahun {{ $year }}@if($org) — {{ $org->name }}@endif</h2>

    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Nama</th>
                <th>No IC</th>
                <th>No Ahli</th>
                <th>Status</th>
                <th>Jumlah (RM)</th>
                <th>Tarikh Bayar</th>
            </tr>
        </thead>
        <tbody>
            @foreach($members as $i => $m)
                @php $fee = $m->membershipFees->first(); @endphp
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>{{ $m->name }}</td>
                    <td>{{ $m->ic_number ?? '—' }}</td>
                    <td>{{ $m->member_no ?? '—' }}</td>
                    <td class="{{ $fee && in_array($fee->status, ['paid','life_member','exempted']) ? 'badge-paid' : 'badge-due' }}">
                        {{ match($fee?->status) { 'paid' => 'Lunas', 'life_member' => 'Seumur Hidup', 'exempted' => 'Dikecualikan', default => 'Tertunggak' } }}
                    </td>
                    <td>{{ $fee ? number_format((float) $fee->amount, 2) : '0.00' }}</td>
                    <td>{{ $fee?->paid_at?->toDateString() ?? '—' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="summary">
        @php
            $total = $members->count();
            $paid = $members->filter(fn($m) => $m->membershipFees->first() && in_array($m->membershipFees->first()->status, ['paid','life_member','exempted']))->count();
        @endphp
        <p><strong>Jumlah Ahli:</strong> {{ $total }}</p>
        <p><strong>Lunas / Dikecualikan:</strong> {{ $paid }}</p>
        <p><strong>Tertunggak:</strong> {{ $total - $paid }}</p>
    </div>
</body>
</html>
