<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Senarai Kehadiran – {{ $event->title }}</title>
    <style>
        /* ── Base ────────────────────────────────────────────────────── */
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: #111;
            background: #fff;
            font-size: 13px;
        }

        /* ── Screen-only elements ────────────────────────────────────── */
        .screen-only {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            padding: 1.25rem 2rem;
            border-bottom: 1px solid #e5e7eb;
            background: #f9fafb;
        }

        .btn-print {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            padding: 0.55rem 1.25rem;
            background: #111827;
            color: #fff;
            border: none;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
        }

        /* ── Document wrapper ────────────────────────────────────────── */
        .doc-wrapper {
            max-width: 900px;
            margin: 0 auto;
            padding: 2rem;
        }

        /* ── Header ──────────────────────────────────────────────────── */
        .doc-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #111827;
        }

        .doc-header h1 {
            font-size: 18px;
            font-weight: 800;
            line-height: 1.3;
            max-width: 500px;
        }

        .doc-header .meta {
            font-size: 11px;
            color: #6b7280;
            margin-top: 6px;
        }

        .doc-header .stamp {
            text-align: right;
            font-size: 11px;
            color: #6b7280;
            flex-shrink: 0;
        }

        .doc-header .stamp strong { display: block; font-size: 15px; font-weight: 800; color: #111; }

        /* ── Table ───────────────────────────────────────────────────── */
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }

        thead tr {
            background: #111827;
            color: #fff;
        }

        thead th {
            padding: 9px 12px;
            text-align: left;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        tbody tr {
            border-bottom: 1px solid #f3f4f6;
        }

        tbody tr:nth-child(even) {
            background: #f9fafb;
        }

        tbody td {
            padding: 8px 12px;
            vertical-align: top;
        }

        .no-col   { width: 44px;  color: #9ca3af; font-size: 11px; }
        .time-col { width: 140px; font-variant-numeric: tabular-nums; }

        /* ── Empty state ─────────────────────────────────────────────── */
        .empty-row td {
            padding: 2rem;
            text-align: center;
            color: #9ca3af;
            font-style: italic;
        }

        /* ── Footer ──────────────────────────────────────────────────── */
        .doc-footer {
            margin-top: 2rem;
            padding-top: 0.75rem;
            border-top: 1px solid #e5e7eb;
            display: flex;
            justify-content: space-between;
            font-size: 10px;
            color: #9ca3af;
        }

        /* ── Signature box (bottom right) ────────────────────────────── */
        .sig-box {
            margin-top: 3rem;
            display: flex;
            justify-content: flex-end;
        }

        .sig-inner {
            width: 200px;
            text-align: center;
        }

        .sig-line {
            border-top: 1px solid #374151;
            margin-top: 2rem;
            padding-top: 6px;
            font-size: 11px;
            color: #374151;
        }

        /* ── Print overrides ─────────────────────────────────────────── */
        @media print {
            .screen-only { display: none !important; }
            body { font-size: 12px; }
            .doc-wrapper { padding: 0; max-width: 100%; }
            thead tr { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            tbody tr:nth-child(even) { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }
    </style>
</head>
<body>

    {{-- ── Screen-only toolbar ─────────────────────────────────────────── --}}
    <div class="screen-only">
        <div>
            <strong style="font-size:14px;">Senarai Kehadiran</strong>
            <span style="color:#6b7280; margin-left:0.5rem; font-size:12px;">{{ $event->title }}</span>
        </div>
        <button class="btn-print" onclick="window.print()">
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none"
                 viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round"
                    d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2
                       4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2
                       2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
            </svg>
            Cetak
        </button>
    </div>

    <div class="doc-wrapper">

        {{-- ── Document header ──────────────────────────────────────────── --}}
        <div class="doc-header">
            <div>
                <p style="font-size:10px; font-weight:700; text-transform:uppercase;
                           letter-spacing:.08em; color:#6b7280; margin-bottom:4px;">
                    {{ $event->organization?->name ?? 'Semua Organisasi' }} &middot; Senarai Kehadiran
                </p>
                <h1>{{ $event->title }}</h1>
                <p class="meta">
                    {{ $event->start_time->format('l, d F Y \p\a\d\a H:i') }}
                    @if($event->location_or_link)
                        &nbsp;&middot;&nbsp; {{ $event->location_or_link }}
                    @endif
                </p>
            </div>
            <div class="stamp">
                <strong>{{ $rsvps->count() }}</strong>
                peserta hadir<br>
                <span style="display:block; margin-top:4px;">
                    Dicetak: {{ now()->format('d/m/Y H:i') }}
                </span>
            </div>
        </div>

        {{-- ── Attendance table ─────────────────────────────────────────── --}}
        <table>
            <thead>
                <tr>
                    <th class="no-col">#</th>
                    <th>Nama</th>
                    <th>No. Telefon</th>
                    <th>E-mel</th>
                    <th class="time-col">Masa Kemasukan</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rsvps as $i => $rsvp)
                    <tr>
                        <td class="no-col">{{ $i + 1 }}</td>
                        <td style="font-weight:600;">{{ $rsvp->user->name }}</td>
                        <td>{{ $rsvp->user->phone ?? '—' }}</td>
                        <td>{{ $rsvp->user->email }}</td>
                        <td class="time-col">
                            {{ $rsvp->attended_at?->format('H:i:s') ?? '—' }}
                        </td>
                    </tr>
                @empty
                    <tr class="empty-row">
                        <td colspan="5">Tiada ahli yang telah merekodkan kehadiran.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        {{-- ── Signature box ────────────────────────────────────────────── --}}
        <div class="sig-box">
            <div class="sig-inner">
                <div class="sig-line">
                    Diluluskan oleh<br>
                    <strong>{{ $event->organization->name }}</strong>
                </div>
            </div>
        </div>

        {{-- ── Footer ───────────────────────────────────────────────────── --}}
        <div class="doc-footer">
            <span>myWAP &copy; {{ now()->year }}</span>
            <span>Dokumen ini dijana secara automatik oleh sistem.</span>
        </div>

    </div>

</body>
</html>
