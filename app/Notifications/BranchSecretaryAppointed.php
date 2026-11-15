<?php

namespace App\Notifications;

use App\Models\AppSetting;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BranchSecretaryAppointed extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $branchName,
        public readonly string $organizationName,
        public readonly string $loginEmail,
        public readonly string $temporaryPassword,
        public readonly string $loginUrl,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $settings = AppSetting::singleton();
        $logoPath = $settings->system_logo_path ?? '/images/logomywaphorizontal.png';

        return (new MailMessage)
            ->mailer('transactional')
            ->subject('Pelantikan setiausaha cawangan '.$this->branchName)
            ->view('emails.branch-secretary-appointed', [
                'subject' => 'Pelantikan setiausaha cawangan '.$this->branchName,
                'secretaryName' => $notifiable->name,
                'branchName' => $this->branchName,
                'organizationName' => $this->organizationName,
                'loginEmail' => $this->loginEmail,
                'temporaryPassword' => $this->temporaryPassword,
                'loginUrl' => $this->loginUrl,
                'logoUrl' => url($logoPath),
                'appName' => config('app.name'),
            ]);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Pelantikan setiausaha cawangan',
            'content' => 'Anda dilantik sebagai setiausaha cawangan '.$this->branchName.'. Butiran log masuk akaun admin cawangan telah dihantar ke emel anda.',
            'type' => 'branch_secretary_appointed',
            'action_type' => null,
            'action_id' => null,
            'action_label' => 'Buka profil',
            'action_url' => route('profile.edit'),
        ];
    }
}
