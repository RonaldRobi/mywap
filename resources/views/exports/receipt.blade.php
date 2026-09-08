<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $title }} - {{ $org_name }}</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: "DejaVu Sans", sans-serif; font-size: 11px; color: #1f2937; margin: 0; padding: 28px 34px; }
        .accent { color: {{ $org_color ?? '#1d4ed8' }}; }

        .brandbar { width: 100%; border-bottom: 3px solid {{ $org_color ?? '#1d4ed8' }}; padding-bottom: 12px; margin-bottom: 18px; }
        .brandbar table { width: 100%; border-collapse: collapse; }
        .brand-logo img { max-height: 52px; max-width: 150px; }
        .brand-logo .org { font-size: 17px; font-weight: bold; color: {{ $org_color ?? '#1d4ed8' }}; margin-top: 4px; }
        .brand-logo .sub { font-size: 10px; color: #6b7280; letter-spacing: 1px; }
        .stamp-cell { text-align: right; vertical-align: middle; }
        .stamp {
            display: inline-block;
            border: 3px solid #16a34a;
            color: #16a34a;
            font-size: 22px;
            font-weight: bold;
            letter-spacing: 4px;
            padding: 6px 18px;
            border-radius: 6px;
            opacity: .85;
        }

        h1 { font-size: 16px; margin: 0 0 14px; color: {{ $org_color ?? '#1d4ed8' }}; }

        .block { margin-bottom: 14px; }
        .section-title { font-size: 10px; text-transform: uppercase; letter-spacing: 1px; color: #6b7280; margin-bottom: 6px; }

        table.meta { width: 100%; border-collapse: collapse; }
        table.meta td { padding: 3px 0; vertical-align: top; }
        table.meta td.k { width: 170px; color: #6b7280; }
        table.meta td.v { font-weight: bold; }

        table.kv { width: 100%; border-collapse: collapse; }
        table.kv td { padding: 3px 0; vertical-align: top; }
        table.kv td.k { width: 170px; color: #6b7280; }

        table.items { width: 100%; border-collapse: collapse; margin-top: 4px; }
        table.items th {
            background: {{ $org_color ?? '#1d4ed8' }}; color: #ffffff; text-align: left;
            font-size: 10px; text-transform: uppercase; letter-spacing: .5px; padding: 7px 9px;
        }
        table.items td { border-bottom: 1px solid #e5e7eb; padding: 8px 9px; vertical-align: top; }
        table.items td.amt { text-align: right; white-space: nowrap; font-weight: bold; }
        table.items td.ctr { text-align: center; }
        table.items tr:last-child td { border-bottom: none; }
        .note { font-size: 9px; color: #6b7280; margin-top: 2px; }

        .total-row { margin-top: 12px; text-align: right; }
        .total-box {
            display: inline-block; background: #f3f4f6; border-left: 4px solid {{ $org_color ?? '#1d4ed8' }};
            padding: 8px 16px; font-size: 14px; font-weight: bold;
        }

        .signatures { width: 100%; margin-top: 34px; table-layout: fixed; }
        .signatures td { width: 50%; font-size: 10px; color: #374151; vertical-align: top; }
        .sign-line { border-top: 1px solid #9ca3af; margin-top: 26px; padding-top: 5px; }

        .footer { margin-top: 26px; padding-top: 10px; border-top: 1px solid #d1d5db; font-size: 9px; color: #9ca3af; text-align: center; }
    </style>
</head>
<body>
    <div class="brandbar">
        <table>
            <tr>
                <td class="brand-logo">
                    @if ($org_logo)
                        <img src="{{ $org_logo }}" alt="{{ $org_name }}">
                    @else
                        <div class="org">{{ $org_name }}</div>
                        <div class="sub">RESIT RASMI</div>
                    @endif
                    @if ($org_logo)
                        <div class="org">{{ $org_name }}</div>
                    @endif
                </td>
                <td class="stamp-cell">
                    <span class="stamp">{{ $status_label }}</span>
                </td>
            </tr>
        </table>
    </div>

    <h1>{{ $title }}</h1>

    <table class="meta">
        <tr>
            <td class="k">No. Resit</td>
            <td class="v">{{ $receipt_no }}</td>
        </tr>
        <tr>
            <td class="k">Tarikh Bayaran</td>
            <td>{{ $paid_at }}</td>
        </tr>
        <tr>
            <td class="k">Kaedah Bayaran</td>
            <td>{{ $payment_method }}</td>
        </tr>
    </table>

    @if (count($bill_to) || count($extra))
        <div class="block">
            @if (count($bill_to))
                <div class="section-title">Dibayar Oleh</div>
                <table class="kv">
                    @foreach ($bill_to as $row)
                        <tr><td class="k">{{ $row['label'] }}</td><td class="v">{{ $row['value'] }}</td></tr>
                    @endforeach
                </table>
            @endif
            @if (count($extra))
                <div class="section-title" style="margin-top:10px;">Butiran</div>
                <table class="kv">
                    @foreach ($extra as $row)
                        <tr><td class="k">{{ $row['label'] }}</td><td>{{ $row['value'] }}</td></tr>
                    @endforeach
                </table>
            @endif
        </div>
    @endif

    <div class="section-title">Butiran Pembayaran</div>
    <table class="items">
        <thead>
            <tr>
                <th>Perkara</th>
                @if ($items_have_qty)
                    <th style="width:60px;">Kuantiti</th>
                    <th style="width:90px; text-align:right;">Harga</th>
                @endif
                <th style="width:130px; text-align:right;">Amaun (RM)</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($items as $item)
                <tr>
                    <td>
                        {{ $item['label'] }}
                        @if (! empty($item['note']))
                            <div class="note">{{ $item['note'] }}</div>
                        @endif
                    </td>
                    @if ($items_have_qty)
                        <td class="ctr">{{ $item['qty'] ?? '-' }}</td>
                        <td class="amt">{{ $item['unit_label'] ?? '-' }}</td>
                    @endif
                    <td class="amt">{{ $item['amount_label'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="total-row">
        <span class="total-box">Jumlah Dibayar: {{ $total }}</span>
    </div>

    <table class="signatures">
        <tr>
            <td>
                <div class="sign-line">{{ $org_name }}</div>
            </td>
            <td>
                <div class="sign-line">Tandatangan Penerima</div>
            </td>
        </tr>
    </table>

    <div class="footer">
        {{ $footer_note }}<br>
        Dijana pada {{ $generated_at }}
    </div>
</body>
</html>
