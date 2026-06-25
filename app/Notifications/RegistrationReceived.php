<?php

namespace App\Notifications;

use App\Models\EmailTemplate;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RegistrationReceived extends Notification implements ShouldQueue
{
    public $connection = 'database';

    public function __construct(
        public $user,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $template = EmailTemplate::forKey('registration_received');

        $data = [
            'name' => $notifiable->name,
            'member_no' => $notifiable->member_no ?? '—',
            'organization' => $notifiable->organization?->name ?? '—',
            'branch' => $notifiable->branch?->name ?? 'Tidak Berkenaan',
            'fee' => number_format($notifiable->organization?->fee_amount ?? 0, 2),
        ];

        $subject = $template?->renderSubject($data) ?? 'Pendaftaran Diterima - myWAP';
        $body = $template?->renderBody($data) ?? 'Pendaftaran anda telah diterima.';

        return (new MailMessage)
            ->subject($subject)
            ->line(nl2br(e($body)));
    }
}
