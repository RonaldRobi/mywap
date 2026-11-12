<?php

namespace App\Notifications;

use App\Models\AppSetting;
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
        $organization = $notifiable->organization;
        $organizationName = $organization?->name ?? 'myWAP';
        $loginUrl = $this->loginLink ?: route('login');

        $data = [
            'name' => $notifiable->name,
            'member_no' => $notifiable->member_no ?? '-',
            'organization' => $organizationName,
            'branch' => $notifiable->branch?->name ?? 'Tidak Berkenaan',
            'email' => $notifiable->email ?? '-',
            'login_link' => $loginUrl,
        ];

        $subject = $template?->renderSubject($data)
            ?? "Selamat datang ke {$organizationName} - myWAP";

        $body = $template?->renderBody($data)
            ?? "Assalamualaikum {$data['name']},\n\n"
                ."Selamat datang ke {$organizationName}! Pendaftaran anda sebagai ahli telah berjaya. "
                .'Akaun myWAP anda kini aktif dan sedia digunakan.';

        $settings = AppSetting::singleton();

        return (new MailMessage)
            ->mailer('transactional')
            ->subject($subject)
            ->view('emails.registration-activated', [
                'subject' => $subject,
                'body' => $body,
                'name' => $data['name'],
                'memberNo' => $data['member_no'],
                'email' => $data['email'],
                'organizationName' => $organizationName,
                'organizationColor' => $organization?->color_theme ?? '#2F6B32',
                'organizationLogoUrl' => $this->logoUrl($organization?->logo_path),
                'appLogoUrl' => $this->logoUrl(
                    $settings->system_logo_path ?? '/images/logomywaphorizontal.png'
                ),
                'loginUrl' => $loginUrl,
                'appName' => config('app.name'),
            ]);
    }

    private function logoUrl(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        if (! str_starts_with($path, '/')) {
            $path = '/storage/'.ltrim($path, '/');
        }

        return url($path);
    }
}
