@extends('emails.layout')

@section('content')
    <tr>
        <td style="padding:24px 32px 32px;font-size:15px;line-height:1.7;color:#334155;">
            <div style="max-width:480px;margin:0 auto;">
                {!! nl2br(e($body)) !!}

                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:20px 0;">
                    <tr>
                        <td style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:16px 20px;">
                            <p style="margin:0 0 6px;font-size:12px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.05em;">No Pendaftaran</p>
                            <p style="margin:0;font-size:18px;font-weight:700;color:#0f172a;letter-spacing:.03em;">{{ $registrationNo }}</p>

                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-top:12px;font-size:14px;">
                                <tr>
                                    <td style="padding:3px 0;color:#64748b;">Program</td>
                                    <td align="right" style="padding:3px 0;font-weight:600;color:#0f172a;">{{ $eventTitle }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:3px 0;color:#64748b;">Tarikh</td>
                                    <td align="right" style="padding:3px 0;font-weight:600;color:#0f172a;">{{ $eventDate }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:3px 0;color:#64748b;">Lokasi</td>
                                    <td align="right" style="padding:3px 0;font-weight:600;color:#0f172a;">{{ $location }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:3px 0;color:#64748b;">Status Bayaran</td>
                                    <td align="right" style="padding:3px 0;font-weight:700;color:#059669;">{{ $paymentStatus }}</td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>

                <p style="margin:0;font-size:13px;color:#64748b;">
                    Sila simpan No Pendaftaran ini untuk semakan kehadiran pada hari program.
                </p>
            </div>
        </td>
    </tr>
@endsection
