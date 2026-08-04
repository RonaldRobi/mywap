<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Surat Pengesahan Keahlian</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 12px; color: #1f2937; margin: 0; padding: 0; }
        .letterhead { text-align: center; border-bottom: 3px solid #1f2937; padding-bottom: 10px; }
        .letterhead .org { font-size: 18px; font-weight: bold; letter-spacing: 0.5px; }
        .letterhead .sub { font-size: 11px; color: #6b7280; margin-top: 2px; }
        .title { text-align: center; font-size: 15px; font-weight: bold; margin: 22px 0 4px; text-transform: uppercase; letter-spacing: 1px; }
        .underline { border-bottom: 1px solid #1f2937; width: 60%; margin: 0 auto 22px; }
        .ref { margin-bottom: 16px; }
        .ref p { margin: 2px 0; }
        .body p { line-height: 1.7; text-align: justify; }
        .details { width: 100%; border-collapse: collapse; margin: 18px 0 24px; }
        .details td { border: 1px solid #d1d5db; padding: 8px 12px; }
        .details td.label { width: 36%; background: #f9fafb; font-weight: bold; }
        .sign { margin-top: 48px; }
        .sign .line { border-bottom: 1px solid #1f2937; width: 240px; margin-top: 64px; }
        .sign p { margin: 4px 0; font-size: 11px; color: #374151; }
        .footer { margin-top: 40px; font-size: 10px; color: #9ca3af; text-align: center; }
    </style>
</head>
<body>
    <div class="letterhead">
        <div class="org">{{ $member['organization'] ?? 'Organisasi' }}</div>
        <div class="sub">Sistem Keahlian myWAP · Pengurusan Ahli &amp; Program</div>
    </div>

    <div class="title">Surat Pengesahan Keahlian</div>
    <div class="underline"></div>

    <div class="ref">
        <p><strong>Rujukan:</strong> {{ $member['member_no'] ?? '—' }}</p>
        <p><strong>Tarikh:</strong> {{ $member['issued_at'] }}</p>
    </div>

    <div class="body">
        <p>Dengan segala hormatnya, perkara di atas adalah dirujuk.</p>
        <p>
            Adalah dengan ini disahkan bahawa <strong>{{ $member['name'] }}</strong>
            (No. Kad Pengenalan: <strong>{{ $member['ic_number'] ?? '—' }}</strong>)
            adalah merupakan ahli yang berdaftar dan diiktiraf di bawah
            <strong>{{ $member['organization'] ?? 'Organisasi' }}</strong> melalui
            Sistem Keahlian myWAP.
        </p>
        <p>Butiran keahlian adalah seperti berikut:</p>
    </div>

    <table class="details">
        <tr><td class="label">Nama Penuh</td><td>{{ $member['name'] }}</td></tr>
        <tr><td class="label">No. Ahli</td><td>{{ $member['member_no'] ?? '—' }}</td></tr>
        <tr><td class="label">No. Kad Pengenalan</td><td>{{ $member['ic_number'] ?? '—' }}</td></tr>
        <tr><td class="label">Organisasi</td><td>{{ $member['organization'] ?? '—' }}</td></tr>
        <tr><td class="label">Cawangan</td><td>{{ $member['branch'] ?? '—' }}</td></tr>
        <tr><td class="label">Negeri</td><td>{{ $member['state'] ?? '—' }}</td></tr>
        <tr><td class="label">Tarikh Mula Keahlian</td><td>{{ $member['joined_at'] ?? '—' }}</td></tr>
        <tr><td class="label">Emel</td><td>{{ $member['email'] ?? '—' }}</td></tr>
        <tr><td class="label">Telefon</td><td>{{ $member['phone'] ?? '—' }}</td></tr>
    </table>

    <div class="body">
        <p>
            Surat ini dikeluarkan sebagai bukti pengesahan keahlian dan boleh digunakan
            untuk urusan-urusan yang memerlukan pengesahan status keahlian. Sekiranya
            terdapat sebarang pertanyaan, sila hubungi pihak pengurusan organisasi.
        </p>
        <p>Sekian, terima kasih.</p>
    </div>

    <div class="sign">
        <div class="line"></div>
        <p><strong>Yang benar,</strong></p>
        <p>Setiausaha / Pendaftar</p>
        <p>{{ $member['organization'] ?? 'Organisasi' }}</p>
    </div>

    <div class="footer">Dokumen ini dijana secara automatik oleh Sistem Keahlian myWAP.</div>
</body>
</html>
