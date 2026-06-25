<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <title>{{ $subject ?? 'myWAP' }}</title>
    <style type="text/css">
        body { margin: 0; padding: 0; background-color: #f4f4f5; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; }
        table { border-collapse: collapse; }
        .wrapper { width: 100%; table-layout: fixed; background-color: #f4f4f5; padding: 24px 0; }
        .main { max-width: 560px; margin: 0 auto; background-color: #ffffff; border-radius: 16px; overflow: hidden; }
    </style>
</head>
<body style="margin:0;padding:0;background-color:#f4f4f5;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,'Helvetica Neue',Arial,sans-serif;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f4f4f5;padding:24px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;margin:0 auto;background-color:#ffffff;border-radius:16px;overflow:hidden;">

                    {{-- Header with Logo --}}
                    <tr>
                        <td style="padding:32px 32px 24px;text-align:center;background-color:#ffffff;">
                            @if ($logoUrl)
                                <img src="{{ $logoUrl }}" alt="myWAP" style="max-height:48px;width:auto;border:0;outline:none;display:inline-block;">
                            @else
                                <span style="font-size:22px;font-weight:800;color:#1e293b;">myWAP</span>
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:0 32px;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td style="height:1px;background:linear-gradient(to right,transparent,#e2e8f0,transparent);font-size:0;line-height:0;">&nbsp;</td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    {{-- Body Content --}}
                    <tr>
                        <td style="padding:24px 32px 32px;font-size:15px;line-height:1.7;color:#334155;">
                            <div style="max-width:480px;margin:0 auto;">
                                {!! nl2br(e($body)) !!}
                            </div>
                        </td>
                    </tr>

                    {{-- Divider --}}
                    <tr>
                        <td style="padding:0 32px;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td style="height:1px;background:linear-gradient(to right,transparent,#e2e8f0,transparent);font-size:0;line-height:0;">&nbsp;</td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    {{-- Footer --}}
                    <tr>
                        <td style="padding:20px 32px 28px;text-align:center;font-size:12px;color:#94a3b8;line-height:1.6;">
                            <div style="max-width:480px;margin:0 auto;">
                                Ini adalah e-mel automatik daripada {{ $appName ?? 'myWAP' }}.<br>
                                Sila jangan balas e-mel ini. Jika ada sebarang pertanyaan, sila hubungi pihak {{ $appName ?? 'myWAP' }}.
                            </div>
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>
</body>
</html>
