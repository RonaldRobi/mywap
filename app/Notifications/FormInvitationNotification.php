<?php

namespace App\Notifications;

use App\Models\AppSetting;
use App\Models\EmailTemplate;
use App\Models\Form;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class FormInvitationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Form $form,
        public string $recipientName = '',
    ) {
        // Ensure recipientName is never null — the on-demand notifiable
        // (Notification::route) has no ->name property, so we must have a fallback.
        if ($this->recipientName === '') {
            $this->recipientName = 'Penerima';
        }
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $template = EmailTemplate::forKey('form_invitation');

        $data = [
            'name' => $this->recipientName ?: $notifiable->name,
            'form_title' => $this->form->title,
            'form_link' => $this->form->public_url,
            'organization' => $this->form->organization?->name ?? 'myWAP',
        ];

        $subject = $template?->renderSubject($data) ?? "Borang: {$this->form->title}";
        $body = $template?->renderBody($data) ?? "Anda dijemput untuk mengisi borang \"{$this->form->title}\".";

        $settings = AppSetting::singleton();
        $logoPath = $settings->system_logo_path ?? '/images/logomywaphorizontal.png';
        $logoUrl = $template?->headerImageUrl() ?? url($logoPath);

        return (new MailMessage)
            ->subject($subject)
            ->view('emails.form-invitation', [
                'subject' => $subject,
                'body' => $body,
                'name' => $data['name'],
                'formTitle' => $this->form->title,
                'formLink' => $this->form->public_url,
                'organization' => $data['organization'],
                'logoUrl' => $logoUrl,
                'appName' => config('app.name'),
            ]);
    }
}
