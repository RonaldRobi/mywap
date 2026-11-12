@extends('emails.layout')

@section('content')
    <tr>
        <td style="padding:24px 32px 32px;font-size:15px;line-height:1.7;color:#334155;">
            <div style="max-width:480px;margin:0 auto;">
                {!! nl2br(e($body)) !!}

                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:24px 0;">
                    <tr>
                        <td align="center">
                            <a href="{{ $registerUrl }}" style="display:inline-block;background-color:#2F6B32;color:#ffffff;text-decoration:none;font-weight:700;font-size:15px;padding:14px 32px;border-radius:12px;">
                                Daftar Sebagai Ahli
                            </a>
                        </td>
                    </tr>
                </table>

                <p style="margin:0;font-size:13px;color:#64748b;">
                    Atau salin pautan ini: <a href="{{ $registerUrl }}" style="color:#2F6B32;">{{ $registerUrl }}</a>
                </p>
            </div>
        </td>
    </tr>
@endsection
