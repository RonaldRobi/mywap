@extends('emails.layout')

@section('content')
    <tr>
        <td style="padding:24px 32px 32px;font-size:15px;line-height:1.7;color:#334155;">
            <div style="max-width:520px;margin:0 auto;">
                <p style="margin:0 0 12px;">Assalamualaikum {{ $secretaryName }},</p>

                <p style="margin:0 0 12px;">
                    Akses anda sebagai Setiausaha Cawangan dalam sistem myWAP telah diaktifkan untuk
                    <strong style="color:#0f172a;">{{ $branchName }}</strong> di {{ $organizationName }}.
                </p>

                <p style="margin:0 0 12px;">
                    Akses ini digunakan untuk mengurus urusan pentadbiran cawangan dalam myWAP. Ia tidak mengubah jawatan rasmi anda dalam organisasi.
                </p>

                <p style="margin:0 0 12px;">
                    Gunakan butiran di bawah untuk log masuk ke akaun admin cawangan. Akaun ini dikongsi oleh setiausaha yang diberi akses.
                </p>

                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:20px 0;background-color:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;">
                    <tr>
                        <td style="padding:16px 18px;font-size:14px;line-height:1.8;color:#334155;">
                            <p style="margin:0 0 8px;"><strong style="color:#0f172a;">Emel log masuk:</strong> {{ $loginEmail }}</p>
                            <p style="margin:0;"><strong style="color:#0f172a;">Kata laluan sementara:</strong> {{ $temporaryPassword }}</p>
                        </td>
                    </tr>
                </table>

                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 20px;background-color:#fff7ed;border:1px solid #fed7aa;border-radius:12px;">
                    <tr>
                        <td style="padding:14px 18px;font-size:14px;line-height:1.7;color:#9a3412;">
                            <strong>Penting:</strong> kata laluan ini hanya untuk kegunaan sementara. Selepas log masuk, anda <strong>wajib</strong> menukarnya di halaman Profil sebelum meneruskan.
                        </td>
                    </tr>
                </table>

                <table role="presentation" cellpadding="0" cellspacing="0">
                    <tr>
                        <td style="border-radius:10px;background-color:#123D2A;">
                            <a href="{{ $loginUrl }}" style="display:inline-block;padding:13px 22px;font-size:15px;font-weight:700;color:#ffffff;text-decoration:none;">Log Masuk ke myWAP</a>
                        </td>
                    </tr>
                </table>

                <p style="margin:22px 0 0;font-size:13px;line-height:1.7;color:#64748b;">
                    Jangan kongsi emel ini. Jika anda tidak sepatutnya dilantik sebagai setiausaha, sila hubungi pentadbir {{ $appName }}.
                </p>

                <p style="margin:20px 0 0;">
                    Salam,<br>
                    <strong style="color:#0f172a;">{{ $appName }}</strong>
                </p>
            </div>
        </td>
    </tr>
@endsection
