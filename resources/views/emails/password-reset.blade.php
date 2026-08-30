@extends('emails.layout')

@section('content')
    <tr>
        <td style="padding:24px 32px 32px;font-size:15px;line-height:1.7;color:#334155;">
            <div style="max-width:480px;margin:0 auto;">
                <p style="margin:0 0 16px;">
                    Assalamualaikum <strong style="color:#0f172a;">{{ $name }}</strong>,
                </p>

                <p style="margin:0 0 24px;">
                    {!! nl2br(e($body)) !!}
                </p>

                {{-- Reset Password Button --}}
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 24px;">
                    <tr>
                        <td align="center" style="padding:8px 0;">
                            <a href="{{ $url }}" style="display:inline-block;background-color:#0f172a;color:#ffffff;font-weight:700;font-size:14px;text-decoration:none;padding:14px 32px;border-radius:10px;">
                                Tetapkan Semula Kata Laluan
                            </a>
                        </td>
                    </tr>
                </table>

                <p style="margin:0 0 4px;font-size:13px;color:#64748b;">
                    Jika butang di atas tidak berfungsi, salin pautan berikut ke pelayar anda:
                </p>
                <p style="margin:0 0 16px;font-size:12px;color:#94a3b8;word-break:break-all;">
                    {{ $url }}
                </p>

                <p style="margin:0 0 4px;font-size:13px;color:#64748b;">
                    Jika anda tidak meminta ini, sila abaikan emel ini.
                </p>

                <p style="margin:16px 0 0;">
                    Salam,<br>
                    <strong style="color:#0f172a;">myWAP</strong>
                </p>
            </div>
        </td>
    </tr>
@endsection
