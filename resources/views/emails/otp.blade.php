@extends('emails.layout')

@section('content')
    <tr>
        <td style="padding:24px 32px 32px;font-size:15px;line-height:1.7;color:#334155;">
            <div style="max-width:480px;margin:0 auto;">

                {{-- Purpose Label --}}
                <p style="margin:0 0 16px;font-size:13px;font-weight:600;color:#10b981;text-transform:uppercase;letter-spacing:1px;">
                    {{ $purpose ?? 'Log Masuk' }}
                </p>

                {{-- Greeting --}}
                <p style="margin:0 0 16px;">
                    Assalamualaikum <strong style="color:#0f172a;">{{ $name }}</strong>,
                </p>

                {{-- Instruction --}}
                <p style="margin:0 0 24px;">
                    Gunakan kod OTP di bawah untuk melengkapkan log masuk ke akaun myWAP anda. Kod ini sah untuk <strong>5 minit</strong>.
                </p>

                {{-- OTP Code Box --}}
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 24px;">
                    <tr>
                        <td align="center" style="padding:24px 32px;background-color:#f0fdf4;border:2px solid #10b981;border-radius:12px;">
                            <div style="font-family:'Courier New',Courier,monospace;font-size:36px;font-weight:700;color:#065f46;letter-spacing:10px;text-align:center;line-height:1.2;">
                                {{ $code }}
                            </div>
                        </td>
                    </tr>
                </table>

                {{-- Note --}}
                <p style="margin:0 0 4px;font-size:13px;color:#64748b;">
                    Jika anda tidak meminta kod ini, sila abaikan email ini.
                </p>

                {{-- Closing --}}
                <p style="margin:16px 0 0;">
                    Salam,<br>
                    <strong style="color:#0f172a;">myWAP</strong>
                </p>

            </div>
        </td>
    </tr>
@endsection
