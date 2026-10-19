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

        /* ── Layout 1: QR only (A4) ────────────────────────────────────── */
        .sheet-qr {
            width: 210mm;
            height: 297mm;
            margin: 22px auto;
            background: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 18mm;
            overflow: hidden;
        }
        .sheet-qr svg {
            display: block;
            width: 168mm;
            height: 168mm;
        }

        /* ── Layout 2: programme poster (1080 × 1350 px, 4:5) ─────────── */
        .poster-stage {
            width: 100%;
            overflow: hidden;
            display: flex;
            justify-content: center;
        }

        .poster {
            width: 1080px;
            height: 1350px;
            flex: none;
            margin: 22px 0;
            padding: 56px;
            background: var(--off-white);
            display: flex;
            flex-direction: column;
            overflow: hidden;
            transform-origin: top center;
        }

        .brandbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 40px;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .brand-mark {
            position: relative;
            width: 38px;
            height: 38px;
            flex: none;
            background: var(--green);
        }
        .brand-mark::after {
            content: "";
            position: absolute;
            inset: 9px;
            border: 2.5px solid var(--off-white);
        }

        .brand-text {
            font-size: 38px;
            line-height: 0.95;
            font-weight: 800;
            letter-spacing: -1.8px;
            color: var(--navy);
        }

        .brand-caption {
            margin-top: 5px;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 0.22em;
            text-transform: uppercase;
            color: var(--muted);
        }

        .system-logo {
            display: block;
            max-height: 48px;
            max-width: 230px;
            object-fit: contain;
        }

        .org-brand {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 16px;
            max-width: 420px;
        }

        .org-logo {
            display: block;
            max-height: 68px;
            max-width: 190px;
            object-fit: contain;
        }

        .org-fallback {
            width: 64px;
            height: 64px;
            flex: none;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 1px solid var(--line);
            background: #fff;
            font-size: 21px;
            font-weight: 800;
            color: var(--green);
        }

        .org-meta { text-align: right; }
        .org-meta .label {
            display: block;
            margin-bottom: 4px;
            font-size: 9px;
            font-weight: 700;
            letter-spacing: 0.2em;
            text-transform: uppercase;
            color: var(--muted);
        }
        .org-meta .name {
            display: block;
            font-size: 16px;
            line-height: 1.3;
            font-weight: 800;
            color: var(--navy);
        }

        .hero {
            position: relative;
            height: 380px;
            margin-top: 28px;
            flex: none;
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
            padding: 32px;
            color: var(--off-white);
        }
        .hero-fallback::before,
        .hero-fallback::after {
            content: "";
            position: absolute;
            border: 2px solid rgba(111, 191, 138, 0.42);
            border-radius: 50%;
        }
        .hero-fallback::before {
            width: 520px;
            height: 520px;
            right: -140px;
            top: -210px;
        }
        .hero-fallback::after {
            width: 260px;
            height: 260px;
            left: -90px;
            bottom: -130px;
        }
        .hero-fallback span {
            position: relative;
            z-index: 1;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.22em;
            text-transform: uppercase;
            color: var(--soft-green);
        }

        .content { margin-top: 30px; }

        .kicker {
            display: flex;
            align-items: center;
            gap: 16px;
            font-size: 14px;
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
            margin: 16px 0 0;
            font-size: 64px;
            line-height: 1.04;
            letter-spacing: -2.4px;
            font-weight: 800;
            color: var(--navy);
            max-width: 920px;
            overflow-wrap: break-word;
        }

        .meta {
            display: grid;
            grid-template-columns: 1fr 1fr 1.45fr;
            gap: 32px;
            margin-top: 26px;
            padding-top: 20px;
            border-top: 1px solid var(--line);
        }

        .meta .label {
            display: block;
            margin-bottom: 6px;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.16em;
            text-transform: uppercase;
            color: var(--muted);
        }

        .meta .value {
            display: block;
            font-size: 22px;
            font-weight: 700;
            line-height: 1.35;
            color: var(--navy);
            word-break: break-word;
        }

        /* ── QR block ──────────────────────────────────────────────────── */
        .qr-block {
            margin-top: auto;
            display: grid;
            grid-template-columns: 1fr 240px;
            gap: 38px;
            align-items: center;
            padding: 34px;
            background: #fff;
            border: 1px solid rgba(7, 21, 37, 0.09);
            border-top: 7px solid var(--green);
        }

        .qr-kicker {
            margin: 0 0 10px;
            font-size: 13px;
            font-weight: 800;
            letter-spacing: 0.2em;
            text-transform: uppercase;
            color: var(--green);
        }

        .qr-title {
            margin: 0;
            font-size: 34px;
            line-height: 1.1;
            letter-spacing: -0.8px;
            font-weight: 800;
            color: var(--navy);
        }

        .qr-copy {
            margin: 14px 0 0;
            font-size: 21px;
            line-height: 1.5;
            color: #33443a;
            max-width: 500px;
        }
        .qr-copy strong { color: var(--navy); }

        .qr-note {
            margin: 14px 0 0;
            font-size: 13px;
            font-weight: 600;
            letter-spacing: 0.02em;
            color: var(--muted);
        }

        .qr-frame {
            width: 240px;
            padding: 13px;
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
            gap: 30px;
            margin-top: 22px;
            padding-top: 12px;
            border-top: 1px solid var(--line);
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            color: var(--muted);
        }

        /* ── Print ─────────────────────────────────────────────────────── */
        @page {
            size: {{ $layout === 'poster' ? '1080px 1350px' : 'A4 portrait' }};
            margin: 0;
        }

        @media print {
            html, body { background: #fff; }
            .screen-bar { display: none !important; }
            .poster-stage { display: block; overflow: visible; height: auto !important; }
            .poster {
                margin: 0;
                transform: none !important;
                page-break-after: avoid;
            }
            .sheet-qr {
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
                {{ $layout === 'qr' ? 'QR sahaja · Saiz A4' : 'Poster 4:5 · 1080 × 1350 px' }}
                @if ($layout === 'poster')
                    · Pilih “Fit to page” jika cetak atas A4
                @endif
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
        <div class="sheet-qr">
            {!! $qrSvg !!}
        </div>
    @else
        @php
            $titleLength = mb_strlen($event->title);
            $titleSize = $titleLength > 72 ? '46px' : ($titleLength > 48 ? '54px' : '64px');
            $orgName = $event->organization?->name ?? 'Semua Organisasi';
        @endphp

        <div class="poster-stage" id="poster-stage">
            <div class="poster" id="poster-sheet">
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
                        @if ($organizationLogo)
                            <img class="org-logo" src="{{ $organizationLogo }}" alt="Logo {{ $orgName }}">
                        @else
                            <span class="org-fallback">{{ mb_substr($orgName, 0, 1) }}</span>
                        @endif
                        <div class="org-meta">
                            <span class="label">Anjuran</span>
                            <span class="name">{{ $orgName }}</span>
                        </div>
                    </div>
                </header>

                <div class="hero">
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

        <script>
            (() => {
                const sheet = document.getElementById('poster-sheet');
                const stage = document.getElementById('poster-stage');
                if (!sheet || !stage) return;

                const fit = () => {
                    const scale = Math.min(1, (window.innerWidth - 32) / 1080);
                    sheet.style.transform = `scale(${scale})`;
                    stage.style.height = `${1350 * scale + 44}px`;
                };

                fit();
                window.addEventListener('resize', fit);
                window.addEventListener('beforeprint', () => {
                    sheet.style.transform = 'none';
                    stage.style.height = 'auto';
                });
                window.addEventListener('afterprint', fit);
            })();
        </script>
    @endif

</body>
</html>
