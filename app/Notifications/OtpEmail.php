<?php

namespace App\Notifications;

use App\Models\EmailTemplate;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OtpEmail extends Notification implements ShouldQueue
{
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

        return (new MailMessage)
            ->subject($subject)
            ->line(nl2br(e($body)));
    }
}
