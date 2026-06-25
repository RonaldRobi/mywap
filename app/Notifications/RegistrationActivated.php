<?php

namespace App\Notifications;

use App\Models\EmailTemplate;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RegistrationActivated extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public $user,
        public string $loginLink = '',
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $template = EmailTemplate::forKey('registration_activated');

        $data = [
            'name' => $notifiable->name,
            'member_no' => $notifiable->member_no ?? '—',
            'organization' => $notifiable->organization?->name ?? '—',
            'login_link' => $this->loginLink ?: route('login'),
        ];

        $subject = $template?->renderSubject($data) ?? 'Akaun Anda Telah Diaktifkan - myWAP';
        $body = $template?->renderBody($data) ?? 'Akaun anda telah diaktifkan.';

        return (new MailMessage)
            ->subject($subject)
            ->line(nl2br(e($body)));
    }
}
