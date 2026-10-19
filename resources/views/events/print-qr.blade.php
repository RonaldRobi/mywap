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
        .btn:hover { background: #f4f6f1; }
        .btn-primary {
            background: var(--dark-green);
            border-color: var(--dark-green);
            color: #fff;
        }
        .btn-primary:hover { background: var(--green); }

        /* ── A4 sheet ──────────────────────────────────────────────────── */
        .sheet-a4 {
            width: 210mm;
            height: 297mm;
            margin: 22px auto;
            padding: 17.25mm 0;
            background: #fff;
            overflow: hidden;
        }

        /* Layout 1: QR only */
        .sheet-a4.qr-only {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 18mm;
        }
        .sheet-a4.qr-only svg {
            display: block;
            width: 168mm;
            height: 168mm;
        }

        /* Layout 2: 4:5 poster area inside A4 */
        .poster-frame {
            width: 210mm;
            height: 262.5mm;
            padding: 12mm 14mm 10mm;
            background: #fff;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        .brandbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8mm;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 3mm;
        }

        .brand-mark {
            position: relative;
            width: 8mm;
            height: 8mm;
            flex: none;
            background: var(--green);
        }
        .brand-mark::after {
            content: "";
            position: absolute;
            inset: 1.8mm;
            border: 0.5mm solid #fff;
        }

        .brand-text {
            font-size: 25pt;
            line-height: 0.95;
            font-weight: 800;
            letter-spacing: -1.1px;
            color: var(--navy);
        }

        .brand-caption {
            margin-top: 1.2mm;
            font-size: 6.4pt;
            font-weight: 700;
            letter-spacing: 0.2em;
            text-transform: uppercase;
            color: var(--muted);
        }

        .system-logo {
            display: block;
            max-height: 11mm;
            max-width: 52mm;
            object-fit: contain;
        }

        .org-brand {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 4mm;
            max-width: 100mm;
        }

        .org-logos {
            display: flex;
            align-items: center;
            gap: 3.5mm;
        }

        .org-logo {
            display: block;
            max-height: 13mm;
            max-width: 26mm;
            object-fit: contain;
        }

        .org-fallback {
            width: 11mm;
            height: 11mm;
            flex: none;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 1px solid var(--line);
            background: #fff;
            font-size: 11pt;
            font-weight: 800;
            color: var(--green);
        }

        .org-meta { text-align: right; }
        .org-meta .label {
            display: block;
            margin-bottom: 0.8mm;
            font-size: 6pt;
            font-weight: 700;
            letter-spacing: 0.18em;
            text-transform: uppercase;
            color: var(--muted);
        }
        .org-meta .name {
            display: block;
            font-size: 8.5pt;
            line-height: 1.3;
            font-weight: 800;
            color: var(--navy);
        }

        /* Featured programme artwork is shown whole (4:5), never cropped. */
        .hero-row {
            display: grid;
            grid-template-columns: 104mm 1fr;
            gap: 7mm;
            height: 130mm;
            margin-top: 5mm;
            flex: none;
        }

        .poster-image {
            position: relative;
            width: 104mm;
            height: 130mm;
            overflow: hidden;
            background: #fff;
            border: 1px solid var(--line);
        }

        .poster-image img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            display: block;
        }

        .hero-fallback {
            position: absolute;
            inset: 0;
            display: flex;
            align-items: flex-end;
            padding: 6mm;
            background: var(--dark-green);
            color: #fff;
        }
        .hero-fallback::before,
        .hero-fallback::after {
            content: "";
            position: absolute;
            border: 1.5px solid rgba(111, 191, 138, 0.42);
            border-radius: 50%;
        }
        .hero-fallback::before {
            width: 86mm;
            height: 86mm;
            right: -30mm;
            top: -34mm;
        }
        .hero-fallback::after {
            width: 44mm;
            height: 44mm;
            left: -18mm;
            bottom: -22mm;
        }
        .hero-fallback span {
            position: relative;
            z-index: 1;
            font-size: 6.5pt;
            font-weight: 700;
            letter-spacing: 0.2em;
            text-transform: uppercase;
            color: var(--soft-green);
        }

        .content {
            min-width: 0;
            padding-top: 2mm;
        }

        .kicker {
            display: flex;
            align-items: center;
            gap: 3mm;
            font-size: 6.6pt;
            font-weight: 800;
            letter-spacing: 0.18em;
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
            margin: 3mm 0 0;
            font-size: 21pt;
            line-height: 1.05;
            letter-spacing: -0.7px;
            font-weight: 800;
            color: var(--navy);
            overflow-wrap: break-word;
        }

        .meta {
            display: flex;
            flex-direction: column;
            gap: 3.2mm;
            margin-top: 5mm;
            padding-top: 3.5mm;
            border-top: 1px solid var(--line);
        }

        .meta .label {
            display: block;
            margin-bottom: 1mm;
            font-size: 6pt;
            font-weight: 700;
            letter-spacing: 0.14em;
            text-transform: uppercase;
            color: var(--muted);
        }

        .meta .value {
            display: block;
            font-size: 8.6pt;
            font-weight: 700;
            line-height: 1.35;
            color: var(--navy);
            word-break: break-word;
        }

        /* ── QR block ──────────────────────────────────────────────────── */
        .qr-block {
            margin-top: auto;
            display: grid;
            grid-template-columns: 1fr 54mm;
            gap: 7mm;
            align-items: center;
            padding: 6mm;
            background: #fff;
            border: 1px solid rgba(7, 21, 37, 0.09);
            border-top: 1.6mm solid var(--green);
        }

        .qr-kicker {
            margin: 0 0 2mm;
            font-size: 6.4pt;
            font-weight: 800;
            letter-spacing: 0.2em;
            text-transform: uppercase;
            color: var(--green);
        }

        .qr-title {
            margin: 0;
            font-size: 15pt;
            line-height: 1.1;
            letter-spacing: -0.3px;
            font-weight: 800;
            color: var(--navy);
        }

        .qr-copy {
            margin: 2.5mm 0 0;
            font-size: 9pt;
            line-height: 1.5;
            color: #33443a;
            max-width: 108mm;
        }
        .qr-copy strong { color: var(--navy); }

        .qr-note {
            margin: 2.5mm 0 0;
            font-size: 6.4pt;
            font-weight: 600;
            letter-spacing: 0.02em;
            color: var(--muted);
        }

        .qr-frame {
            width: 54mm;
            padding: 3mm;
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
            margin-top: 4mm;
            padding-top: 2mm;
            border-top: 1px solid var(--line);
            font-size: 6pt;
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
            .sheet-a4 {
                margin: 0;
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
            <span>
                {{ $layout === 'qr' ? 'QR sahaja · A4' : 'Poster 4:5 dalam A4 · latar putih' }}
            </span>
        </div>
        <div class="screen-actions">
            <a class="btn" href="{{ route('events.qr.print', ['event' => $event->id, 'layout' => $layout === 'qr' ? 'poster' : 'qr']) }}">
                {{ $layout === 'qr' ? 'Tukar ke Poster' : 'Tukar ke QR Sahaja' }}
            </a>
            <button class="btn btn-primary" type="button" onclick="window.print()">
                Cetak
            </button>
        </div>
    </div>

    @if ($layout === 'qr')
        <div class="sheet-a4 qr-only">
            {!! $qrSvg !!}
        </div>
    @else
        @php
            $titleLength = mb_strlen($event->title);
            $titleSize = $titleLength > 72 ? '17pt' : ($titleLength > 48 ? '19pt' : '21pt');
            $brandNames = $organizationBrands->pluck('name');
            $brandLabel = $brandNames->count() > 1
                ? 'PKPIM · ABIM · WADAH'
                : ($brandNames->first() ?? 'Semua Organisasi');
        @endphp

        <div class="sheet-a4">
            <div class="poster-frame">
                <header class="brandbar">
                    <div>
                        @if ($systemLogo)
                            <img class="system-logo" src="{{ $systemLogo }}" alt="myWAP">
                        @else
                            <div class="brand">
                                <span class="brand-mark" aria-hidden="true"></span>
                                <span class="brand-text">myWAP</span>
                            </div>
                        @endif
                        <div class="brand-caption">Platform Digital Gerakan</div>
                    </div>

                    <div class="org-brand">
                        @if ($organizationBrands->isNotEmpty())
                            <div class="org-logos">
                                @foreach ($organizationBrands as $brand)
                                    @if ($brand['logo'])
                                        <img class="org-logo" src="{{ $brand['logo'] }}" alt="Logo {{ $brand['name'] }}">
                                    @else
                                        <span class="org-fallback">{{ mb_substr($brand['name'], 0, 1) }}</span>
                                    @endif
                                @endforeach
                            </div>
                        @endif
                        <div class="org-meta">
                            <span class="label">Anjuran</span>
                            <span class="name">{{ $brandLabel }}</span>
                        </div>
                    </div>
                </header>

                <div class="hero-row">
                    <div class="poster-image">
                        @if ($featuredImage)
                            <img src="{{ $featuredImage }}" alt="{{ $event->title }}">
                        @else
                            <div class="hero-fallback"><span>Kehadiran Program</span></div>
                        @endif
                    </div>

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
                </div>

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
                    <span>myWAP &middot; Wadah &middot; ABIM &middot; PKPIM</span>
                    <span>{{ now()->format('d/m/Y') }}</span>
                </footer>
            </div>
        </div>
    @endif

</body>
</html>
