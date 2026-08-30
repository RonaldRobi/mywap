<?php

namespace App\Notifications;

use App\Models\AppSetting;
use App\Models\EmailTemplate;
use App\Models\Registration;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RegistrationConfirmationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Registration $registration,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $registration = $this->registration->loadMissing(['event.organization']);
        $event = $registration->event;

        $paymentStatus = match ($registration->latestPayment?->status) {
            'successful' => 'Berjaya',
            'pending' => 'Menunggu',
            'failed' => 'Gagal',
            default => '—',
        };

        $template = EmailTemplate::forKey('registration_confirmation');

        $data = [
            'name' => $registration->name,
            'registration_no' => $registration->registration_no,
            'event_title' => $event?->title ?? 'Program',
            'event_date' => $event?->start_time?->locale('ms')->isoFormat('D MMM YYYY, h:mm A') ?? '—',
            'location' => $event?->location_or_link ?? '—',
            'payment_status' => $paymentStatus,
        ];

        $subject = $template?->renderSubject($data) ?? "Pengesahan Pendaftaran: {$data['event_title']}";
        $body = $template?->renderBody($data) ?? "Pendaftaran anda telah diterima. No Pendaftaran: {$registration->registration_no}.";

        $settings = AppSetting::singleton();
        $logoUrl = $template?->headerImageUrl() ?? url($settings->system_logo_path ?? '/images/logomywaphorizontal.png');

        return (new MailMessage)
            ->subject($subject)
            ->view('emails.registration-confirmation', [
                'subject' => $subject,
                'body' => $body,
                'name' => $registration->name,
                'registrationNo' => $registration->registration_no,
                'eventTitle' => $data['event_title'],
                'eventDate' => $data['event_date'],
                'location' => $data['location'],
                'paymentStatus' => $data['payment_status'],
                'logoUrl' => $logoUrl,
                'appName' => config('app.name'),
            ]);
    }
}
