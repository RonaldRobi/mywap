@extends('emails.layout')

@section('content')
    <tr>
        <td style="padding:24px 32px 32px;font-size:15px;line-height:1.7;color:#334155;">
            <div style="max-width:480px;margin:0 auto;">
                <p style="margin:0 0 12px;">Assalamualaikum {{ $name }},</p>

                <p style="margin:0 0 12px;">
                    Surat pengesahan keahlian anda bagi {{ $organization }} dilampirkan bersama emel ini.
                </p>

                @if ($memberNo)
                    <p style="margin:0 0 12px;">
                        <strong>No. Ahli:</strong> {{ $memberNo }}
                    </p>
                @endif

                <p style="margin:0 0 12px;">
                    Sila simpan surat ini untuk rujukan urusan rasmi.
                </p>

                <p style="margin:16px 0 0;">
                    Salam,<br>
                    <strong style="color:#0f172a;">{{ $appName }}</strong>
                </p>
            </div>
        </td>
    </tr>
@endsection
