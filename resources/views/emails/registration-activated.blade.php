@extends('emails.layout')

@section('content')
    <tr>
        <td style="padding:8px 32px 0;">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                <tr>
                    <td align="left" style="padding:0 0 16px;">
                        @if (!empty($appLogoUrl))
                            <img src="{{ $appLogoUrl }}" alt="{{ $appName ?? 'myWAP' }}" style="height:36px;width:auto;border:0;outline:none;display:block;">
                        @else
                            <span style="font-size:20px;font-weight:800;color:#1e293b;">{{ $appName ?? 'myWAP' }}</span>
                        @endif
                    </td>
                </tr>
            </table>
        </td>
    </tr>

    <tr>
        <td style="padding:0 32px;">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f4f6f1;border-radius:14px;border-left:4px solid {{ $organizationColor ?? '#2F6B32' }};">
                <tr>
                    <td style="padding:24px;">
                        <table role="presentation" cellpadding="0" cellspacing="0">
                            <tr>
                                @if (!empty($organizationLogoUrl))
                                    <td style="padding-right:14px;vertical-align:middle;">
                                        <img src="{{ $organizationLogoUrl }}" alt="{{ $organizationName }}" style="height:52px;width:auto;max-width:120px;border:0;outline:none;display:block;">
                                    </td>
                                @endif
                                <td style="vertical-align:middle;">
                                    <p style="margin:0 0 6px;font-size:12px;font-weight:700;letter-spacing:0.08em;text-transform:uppercase;color:#4A5A50;">Pendaftaran berjaya</p>
                                    <h1 style="margin:0;font-size:24px;line-height:1.3;font-weight:800;color:#071525;">Selamat datang ke {{ $organizationName }}</h1>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </td>
    </tr>

    <tr>
        <td style="padding:24px 32px 32px;font-size:15px;line-height:1.7;color:#334155;">
            <div style="max-width:480px;margin:0 auto;">
                {!! nl2br(e($body)) !!}

                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:22px 0;background-color:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;">
                    <tr>
                        <td style="padding:16px 18px;font-size:14px;line-height:1.7;color:#334155;">
                            <p style="margin:0 0 6px;"><strong style="color:#0f172a;">No. Ahli:</strong> {{ $memberNo }}</p>
                            <p style="margin:0 0 6px;"><strong style="color:#0f172a;">Organisasi:</strong> {{ $organizationName }}</p>
                            <p style="margin:0;"><strong style="color:#0f172a;">Emel:</strong> {{ $email }}</p>
                        </td>
                    </tr>
                </table>

                <h2 style="margin:0 0 10px;font-size:17px;line-height:1.4;font-weight:800;color:#0f172a;">Cara log masuk kali pertama</h2>
                <ol style="margin:0 0 22px;padding-left:20px;font-size:14px;line-height:1.8;color:#334155;">
                    <li>Buka halaman log masuk myWAP.</li>
                    <li>Masukkan No. Kad Pengenalan anda.</li>
                    <li>Pilih log masuk kali pertama dan tunggu kod OTP dihantar ke emel ini.</li>
                    <li>Masukkan kod OTP, kemudian tetapkan kata laluan baharu anda.</li>
                </ol>

                <table role="presentation" cellpadding="0" cellspacing="0">
                    <tr>
                        <td style="border-radius:10px;background-color:#123D2A;">
                            <a href="{{ $loginUrl }}" style="display:inline-block;padding:13px 22px;font-size:15px;font-weight:700;color:#ffffff;text-decoration:none;">Log Masuk ke myWAP</a>
                        </td>
                    </tr>
                </table>

                <p style="margin:22px 0 0;font-size:13px;line-height:1.7;color:#64748b;">
                    Jika anda tidak pernah mendaftar sebagai ahli {{ $organizationName }}, sila abaikan emel ini atau hubungi pentadbir {{ $appName ?? 'myWAP' }}.
                </p>

                <p style="margin:20px 0 0;">
                    Salam,<br>
                    <strong style="color:#0f172a;">{{ $appName ?? 'myWAP' }}</strong>
                </p>
            </div>
        </td>
    </tr>
@endsection
