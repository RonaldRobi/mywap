<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Surat Pengesahan Keahlian</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 12px; color: #1f2937; margin: 0; padding: 0; }

        .letterhead { padding-bottom: 12px; border-bottom: 3px solid {{ $org_color }}; }
        .letterhead img { width: 100%; max-height: 140px; object-fit: contain; }
        .letterhead .brand { text-align: center; }
        .letterhead .brand .logo { max-height: 64px; max-width: 180px; }
        .letterhead .brand .org { font-size: 18px; font-weight: bold; letter-spacing: 0.5px; color: {{ $org_color }}; }
        .letterhead .brand .sub { font-size: 10px; color: #6b7280; margin-top: 3px; letter-spacing: 0.5px; }

        .title { text-align: center; font-size: 15px; font-weight: bold; margin: 24px 0 4px; text-transform: uppercase; letter-spacing: 1px; color: {{ $org_color }}; }
        .underline { border-bottom: 1px solid {{ $org_color }}; width: 60%; margin: 0 auto 24px; }

        .ref { margin-bottom: 16px; }
        .ref p { margin: 2px 0; }

        .body p { line-height: 1.75; text-align: justify; margin: 0 0 10px; }

        .details { width: 100%; border-collapse: collapse; margin: 18px 0 24px; }
        .details td { border: 1px solid #d1d5db; padding: 8px 12px; }
        .details td.label { width: 36%; background: #f9fafb; font-weight: bold; }

        .sign { margin-top: 44px; }
        .sign .line { border-bottom: 1px solid #1f2937; width: 240px; margin-top: 58px; }
        .sign p { margin: 4px 0; font-size: 11px; color: #374151; }

        .footer { margin-top: 40px; font-size: 9px; color: #9ca3af; text-align: center; }
    </style>
</head>
<body>
    <div class="letterhead">
        @if ($letterhead)
            <img src="{{ $letterhead }}" alt="{{ $organization }}">
        @else
            <div class="brand">
                @if ($org_logo)
                    <img class="logo" src="{{ $org_logo }}" alt="{{ $organization }}">
                @endif
                <div class="org">{{ $organization }}</div>
                <div class="sub">PENGESAHAN KEAHLIAN</div>
            </div>
        @endif
    </div>

    <div class="title">Surat Pengesahan Keahlian</div>
    <div class="underline"></div>

    <div class="ref">
        <p><strong>Rujukan:</strong> {{ $member_no }}</p>
        <p><strong>Tarikh:</strong> {{ $issued_at }}</p>
    </div>

    <div class="body">
        {!! nl2br(e($body)) !!}
    </div>

    <table class="details">
        <tr><td class="label">Nama Penuh</td><td>{{ $name }}</td></tr>
        <tr><td class="label">No. Ahli</td><td>{{ $member_no }}</td></tr>
        <tr><td class="label">No. Kad Pengenalan</td><td>{{ $ic_number }}</td></tr>
        <tr><td class="label">Organisasi</td><td>{{ $organization }}</td></tr>
        <tr><td class="label">Cawangan</td><td>{{ $branch }}</td></tr>
        <tr><td class="label">Negeri</td><td>{{ $state }}</td></tr>
        <tr><td class="label">Tarikh Mula Keahlian</td><td>{{ $joined_at }}</td></tr>
    </table>

    <div class="sign">
        <div class="line"></div>
        <p><strong>Yang benar,</strong></p>
        @if ($signatory_name)
            <p>{{ $signatory_name }}</p>
        @endif
        <p>{{ $signatory_position }}</p>
        <p>{{ $organization }}</p>
    </div>

    <div class="footer">Dokumen ini dijana secara automatik oleh {{ $app_name }}.</div>
</body>
</html>
