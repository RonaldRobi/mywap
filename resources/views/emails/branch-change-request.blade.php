@extends('emails.layout')

@section('content')
    <tr>
        <td style="padding:24px 32px 32px;font-size:15px;line-height:1.7;color:#334155;">
            <div style="max-width:520px;margin:0 auto;">
                <p style="margin:0 0 12px;">Assalamualaikum,</p>

                <p style="margin:0 0 12px;">
                    Satu permohonan pertukaran cawangan telah dihantar dan memerlukan perhatian pihak cawangan.
                </p>

                <table style="width:100%;border-collapse:collapse;margin:16px 0;font-size:14px;">
                    <tr>
                        <td style="padding:8px 0;color:#64748b;">Ahli</td>
                        <td style="padding:8px 0;font-weight:600;color:#0f172a;">{{ $memberName }} ({{ $memberNo }})</td>
                    </tr>
                    <tr>
                        <td style="padding:8px 0;color:#64748b;">Dari cawangan</td>
                        <td style="padding:8px 0;font-weight:600;color:#0f172a;">{{ $fromBranch }}</td>
                    </tr>
                    <tr>
                        <td style="padding:8px 0;color:#64748b;">Ke cawangan</td>
                        <td style="padding:8px 0;font-weight:600;color:#0f172a;">{{ $toBranch }}</td>
                    </tr>
                    <tr>
                        <td style="padding:8px 0;color:#64748b;">Dihantar</td>
                        <td style="padding:8px 0;font-weight:600;color:#0f172a;">{{ $submittedAt }}</td>
                    </tr>
                </table>

                <p style="margin:0 0 12px;">
                    Permohonan ini akan diproses oleh admin organisasi. Emel ini hanya untuk makluman pihak cawangan.
                </p>

                <p style="margin:16px 0 0;">
                    Salam,<br>
                    <strong style="color:#0f172a;">{{ $appName }}</strong>
                </p>
            </div>
        </td>
    </tr>
@endsection
