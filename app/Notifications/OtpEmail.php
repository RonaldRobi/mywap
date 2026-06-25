<?php

namespace App\Notifications;

use App\Models\AppSetting;
use App\Models\EmailTemplate;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OtpEmail extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $code,
        public string $purpose = 'login',
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $key = $this->purpose === 'login' ? 'otp_login' : 'otp_email_verify';
        $template = EmailTemplate::forKey($key);

        $data = [
            'name' => $notifiable->name,
            'code' => $this->code,
            'purpose' => $this->purpose === 'login' ? 'Log Masuk' : 'Pengesahan Emel',
        ];

        $subject = $template
            ? $template->renderSubject($data)
            : ($this->purpose === 'login' ? 'Kod Pengesahan Log Masuk myWAP' : 'Kod Pengesahan Emel myWAP');

        $body = $template
            ? $template->renderBody($data)
            : "Kod OTP anda: {$this->code}";

        $settings = AppSetting::singleton();
        $logoPath = $settings->system_logo_path ?? '/storage/logos/organizations/logomywaphorizontal.png';
        $logoUrl = url($logoPath);

        return (new MailMessage)
            ->subject($subject)
            ->view('emails.otp', [
                'subject' => $subject,
                'body' => $body,
                'name' => $data['name'],
                'code' => $data['code'],
                'purpose' => $data['purpose'],
                'logoUrl' => $logoUrl,
                'appName' => config('app.name'),
            ]);
    }
}
