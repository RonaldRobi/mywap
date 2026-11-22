@extends('emails.layout')

@section('content')
    @php
        $greetingName = trim((string) ($name ?? ''));
        $messageBody = trim((string) ($content ?? ''));
        $bodyAlreadyGreets = \Illuminate\Support\Str::startsWith(
            \Illuminate\Support\Str::lower($messageBody),
            ['assalamualaikum', 'salam sejahtera', 'salam']
        );
    @endphp

    <tr>
        <td style="padding:24px 32px 32px;font-size:15px;line-height:1.7;color:#334155;">
            <div style="max-width:480px;margin:0 auto;">

                @if (! $bodyAlreadyGreets)
                    <p style="margin:0 0 16px;">
                        @if ($greetingName !== '')
                            Assalamualaikum <strong style="color:#0f172a;">{{ $greetingName }}</strong>,
                        @else
                            Assalamualaikum,
                        @endif
                    </p>
                @endif

                <p style="margin:0 0 24px;white-space:pre-line;">
                    {{ $messageBody }}
                </p>

                <p style="margin:16px 0 0;">
                    Salam,<br>
                    <strong style="color:#0f172a;">{{ $appName ?? 'myWAP' }}</strong>
                </p>

            </div>
        </td>
    </tr>
@endsection
