@extends('emails.layout')

@section('content')
    <tr>
        <td style="padding:24px 32px 32px;font-size:15px;line-height:1.7;color:#334155;">
            <div style="max-width:480px;margin:0 auto;">
                {!! nl2br(e($body)) !!}

                <p style="margin:16px 0 0;">
                    Salam,<br>
                    <strong style="color:#0f172a;">myWAP</strong>
                </p>
            </div>
        </td>
    </tr>
@endsection
