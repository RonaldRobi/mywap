<?php

namespace App\Notifications;

use App\Models\EmailTemplate;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewMemberAlert extends Notification
{
    public function __construct(
        public $newMember,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $template = EmailTemplate::forKey('new_member_alert');

        $data = [
            'name' => $this->newMember->name,
            'member_no' => $this->newMember->member_no ?? '—',
            'ic_number' => $this->newMember->ic_number ?? '—',
            'organization' => $this->newMember->organization?->name ?? '—',
            'branch' => $this->newMember->branch?->name ?? 'Tidak Berkenaan',
            'fee' => number_format($this->newMember->organization?->fee_amount ?? 0, 2),
        ];

        $subject = $template?->renderSubject($data) ?? 'Ahli Baru Mendaftar - myWAP';
        $body = $template?->renderBody($data) ?? 'Ahli baru telah mendaftar.';

        return (new MailMessage)
            ->subject($subject)
            ->line(nl2br(e($body)));
    }
}
