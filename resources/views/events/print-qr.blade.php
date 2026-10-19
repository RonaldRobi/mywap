<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QR Kehadiran – {{ $event->title }}</title>
    <style>
        /* ── myWAP palette ─────────────────────────────────────────────── */
        :root {
            --navy: #071525;
            --dark-green: #123d2a;
            --green: #2f6b32;
            --soft-green: #6fbf8a;
            --off-white: #f4f6f1;
            --line: rgba(7, 21, 37, 0.12);
            --muted: #68776d;
        }

        *, *::before, *::after { box-sizing: border-box; }

        html, body {
            margin: 0;
            padding: 0;
            background: #e7ebe5;
            color: var(--navy);
            font-family: "Helvetica Neue", Helvetica, Arial, sans-serif;
            -webkit-font-smoothing: antialiased;
        }

        /* ── Screen toolbar (hidden when printing) ─────────────────────── */
        .screen-bar {
            position: sticky;
            top: 0;
            z-index: 10;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            padding: 12px 20px;
            background: rgba(255, 255, 255, 0.94);
            border-bottom: 1px solid var(--line);
            backdrop-filter: blur(8px);
        }

        .screen-bar .who { min-width: 0; }
        .screen-bar .who strong {
            display: block;
            font-size: 13px;
            font-weight: 800;
            letter-spacing: -0.01em;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 48vw;
        }
        .screen-bar .who span {
            display: block;
            margin-top: 2px;
            font-size: 11px;
            color: var(--muted);
        }

        .screen-actions { display: flex; align-items: center; gap: 8px; flex-shrink: 0; }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            border-radius: 10px;
            border: 1px solid var(--line);
            background: #fff;
            color: var(--navy);
            padding: 9px 14px;
            font: inherit;
            font-size: 12px;
            font-weight: 700;
            text-decoration: none;
            cursor: pointer;
        }
        .btn:hover { background: var(--off-white); }
        .btn-primary {
            background: var(--dark-green);
            border-color: var(--dark-green);
            color: #fff;
        }
        .btn-primary:hover { background: var(--green); }

        /* ── A4 sheet ──────────────────────────────────────────────────── */
        .sheet {
            width: 210mm;
            height: 297mm;
            margin: 22px auto;
            background: var(--off-white);
            overflow: hidden;
            position: relative;
        }

        /* ── Layout 1: QR only ─────────────────────────────────────────── */
        .sheet-qr {
            background: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 18mm;
        }
        .sheet-qr svg {
            display: block;
            width: 168mm;
            height: 168mm;
        }

        /* ── Layout 2: A4 poster ───────────────────────────────────────── */
        .poster {
            display: flex;
            flex-direction: column;
            padding: 13mm 14mm 10mm;
        }

        .brandbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10mm;
        }

        .brand {
            display: inline-flex;
            align-items: center;
            gap: 2.2mm;
            font-size: 12pt;
            font-weight: 800;
            letter-spacing: -0.035em;
            color: var(--navy);
        }
        .brand::before {
            content: "";
            width: 3.2mm;
            height: 3.2mm;
            background: var(--green);
            display: inline-block;
        }

        .org-name {
            max-width: 95mm;
            text-align: right;
            font-size: 7.4pt;
            font-weight: 700;
            letter-spacing: 0.14em;
            text-transform: uppercase;
            color: var(--muted);
            line-height: 1.45;
        }

        .hero {
            position: relative;
            height: 82mm;
            margin-top: 7mm;
            overflow: hidden;
            background: var(--dark-green);
        }

        .hero img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        .hero-fallback {
            position: absolute;
            inset: 0;
            display: flex;
            align-items: flex-end;
            padding: 8mm;
            color: var(--off-white);
        }
        .hero-fallback::before,
        .hero-fallback::after {
            content: "";
            position: absolute;
            border: 1.5px solid rgba(111, 191, 138, 0.42);
            border-radius: 50%;
        }
        .hero-fallback::before {
            width: 105mm;
            height: 105mm;
            right: -28mm;
            top: -42mm;
        }
        .hero-fallback::after {
            width: 52mm;
            height: 52mm;
            left: -18mm;
            bottom: -26mm;
        }
        .hero-fallback span {
            position: relative;
            z-index: 1;
            font-size: 6.8pt;
            font-weight: 700;
            letter-spacing: 0.2em;
            text-transform: uppercase;
            color: var(--soft-green);
        }

        .content { margin-top: 8mm; }

        .kicker {
            display: flex;
            align-items: center;
            gap: 3mm;
            font-size: 7.2pt;
            font-weight: 800;
            letter-spacing: 0.2em;
            text-transform: uppercase;
            color: var(--green);
        }
        .kicker::after {
            content: "";
            flex: 1;
            height: 1px;
            background: rgba(47, 107, 50, 0.28);
        }

        .title {
            margin: 4mm 0 0;
            font-size: 29pt;
            line-height: 1.04;
            letter-spacing: -0.038em;
            font-weight: 800;
            color: var(--navy);
            max-width: 172mm;
            overflow-wrap: break-word;
        }

        .meta {
            display: grid;
            grid-template-columns: 1fr 1fr 1.35fr;
            gap: 6mm;
            margin-top: 8mm;
            padding-top: 4mm;
            border-top: 1px solid var(--line);
        }

        .meta .label {
            display: block;
            margin-bottom: 1.2mm;
            font-size: 6.8pt;
            font-weight: 700;
            letter-spacing: 0.16em;
            text-transform: uppercase;
            color: var(--muted);
        }

        .meta .value {
            display: block;
            font-size: 10.5pt;
            font-weight: 700;
            line-height: 1.35;
            color: var(--navy);
            word-break: break-word;
        }

        /* ── QR block ──────────────────────────────────────────────────── */
        .qr-block {
            margin-top: auto;
            display: grid;
            grid-template-columns: 1fr 72mm;
            gap: 8mm;
            align-items: center;
            padding: 7mm;
            background: #fff;
            border: 1px solid rgba(7, 21, 37, 0.09);
            border-top: 2.2mm solid var(--green);
        }

        .qr-kicker {
            margin: 0 0 2.5mm;
            font-size: 7.2pt;
            font-weight: 800;
            letter-spacing: 0.2em;
            text-transform: uppercase;
            color: var(--green);
        }

        .qr-title {
            margin: 0;
            font-size: 17.5pt;
            line-height: 1.12;
            letter-spacing: -0.025em;
            font-weight: 800;
            color: var(--navy);
        }

        .qr-copy {
            margin: 3mm 0 0;
            font-size: 10.5pt;
            line-height: 1.5;
            color: #33443a;
            max-width: 78mm;
        }
        .qr-copy strong { color: var(--navy); }

        .qr-note {
            margin: 3.5mm 0 0;
            font-size: 7.4pt;
            font-weight: 600;
            letter-spacing: 0.02em;
            color: var(--muted);
        }

        .qr-frame {
            width: 72mm;
            padding: 3.5mm;
            background: #fff;
            border: 1px solid var(--line);
        }
        .qr-frame svg {
            display: block;
            width: 100%;
            height: auto;
        }

        .footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8mm;
            margin-top: 5mm;
            padding-top: 3mm;
            border-top: 1px solid var(--line);
            font-size: 6.8pt;
            font-weight: 700;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            color: var(--muted);
        }

        /* ── Print ─────────────────────────────────────────────────────── */
        @page {
            size: A4 portrait;
            margin: 0;
        }

        @media print {
            html, body { background: #fff; }
            .screen-bar { display: none !important; }
            .sheet {
                margin: 0;
                width: 210mm;
                height: 297mm;
                page-break-after: avoid;
            }
            * {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
        }
    </style>
</head>
<body>

    <div class="screen-bar">
        <div class="who">
            <strong>{{ $event->title }}</strong>
            <span>{{ $layout === 'qr' ? 'QR sahaja' : 'Poster A4' }} · Saiz A4 · Margin tiada</span>
        </div>
        <div class="screen-actions">
            <a class="btn" href="{{ route('events.qr.print', ['event' => $event->id, 'layout' => $layout === 'qr' ? 'poster' : 'qr']) }}">
                {{ $layout === 'qr' ? 'Tukar ke Poster A4' : 'Tukar ke QR Sahaja' }}
            </a>
            <button class="btn btn-primary" type="button" onclick="window.print()">
                Cetak
            </button>
        </div>
    </div>

    @if ($layout === 'qr')
        <div class="sheet sheet-qr">
            {!! $qrSvg !!}
        </div>
    @else
        <div class="sheet poster">
            <header class="brandbar">
                <div class="brand">myWAP</div>
                <div class="org-name">{{ $event->organization?->name ?? 'Semua Organisasi' }}</div>
            </header>

            <div class="hero">
                @if ($featuredImage)
                    <img src="{{ $featuredImage }}" alt="{{ $event->title }}">
                @else
                    <div class="hero-fallback"><span>Kehadiran Program</span></div>
                @endif
            </div>

            @php
                $titleLength = mb_strlen($event->title);
                $titleSize = $titleLength > 72 ? '23pt' : ($titleLength > 48 ? '26pt' : '29pt');
            @endphp

            <main class="content">
                <div class="kicker">
                    Kehadiran Program &middot; {{ $event->category?->label() ?? 'Acara' }}
                </div>

                <h1 class="title" style="font-size: {{ $titleSize }};">{{ $event->title }}</h1>

                <div class="meta">
                    <div>
                        <span class="label">Tarikh</span>
                        <span class="value">{{ $event->start_time->locale('ms')->isoFormat('D MMMM YYYY') }}</span>
                    </div>
                    <div>
                        <span class="label">Masa</span>
                        <span class="value">
                            {{ $event->start_time->locale('ms')->isoFormat('h:mm A') }}
                            @if ($event->end_time)
                                – {{ $event->end_time->locale('ms')->isoFormat('h:mm A') }}
                            @endif
                        </span>
                    </div>
                    <div>
                        <span class="label">{{ $event->type === 'online' ? 'Pautan' : 'Lokasi' }}</span>
                        <span class="value">{{ $event->location_or_link ?: 'Akan dimaklumkan' }}</span>
                    </div>
                </div>
            </main>

            <section class="qr-block">
                <div>
                    <p class="qr-kicker">Imbas untuk hadir</p>
                    <h2 class="qr-title">Sahkan kehadiran anda</h2>
                    <p class="qr-copy">
                        Imbas kod QR ini, kemudian masukkan <strong>No. IC</strong> anda
                        untuk mengesahkan kehadiran.
                    </p>
                    <p class="qr-note">Satu imbasan untuk setiap peserta.</p>
                </div>

                <div class="qr-frame">
                    {!! $qrSvg !!}
                </div>
            </section>

            <footer class="footer">
                <span>myWAP &middot; Sistem Pengurusan Organisasi</span>
                <span>{{ now()->format('d/m/Y') }}</span>
            </footer>
        </div>
    @endif

</body>
</html>
