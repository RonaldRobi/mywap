<?php

namespace App\Notifications;

use App\Models\AppSetting;
use App\Models\EmailTemplate;
use App\Models\Supporter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SupporterInvitationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Supporter $supporter,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $template = EmailTemplate::forKey('supporter_invitation');
        $registerUrl = route('register');

        $data = [
            'name' => $this->supporter->name ?: 'Sahabat',
            'organization' => $this->supporter->organization?->name ?? 'myWAP',
            'register_link' => $registerUrl,
        ];

        $subject = $template?->renderSubject($data) ?? 'Jemputan Menjadi Ahli myWAP';
        $body = $template?->renderBody($data)
            ?? "Anda dijemput mendaftar sebagai ahli melalui pautan berikut:\n\n{$registerUrl}";

        $settings = AppSetting::singleton();
        $logoUrl = $template?->headerImageUrl() ?? url($settings->system_logo_path ?? '/images/logomywaphorizontal.png');

        return (new MailMessage)
            ->subject($subject)
            ->view('emails.supporter-invitation', [
                'subject' => $subject,
                'body' => $body,
                'name' => $data['name'],
                'registerUrl' => $registerUrl,
                'organization' => $data['organization'],
                'logoUrl' => $logoUrl,
                'appName' => config('app.name'),
            ]);
    }
}
