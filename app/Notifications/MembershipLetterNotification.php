<?php

namespace App\Notifications;

use App\Models\AppSetting;
use App\Models\User;
use App\Services\MembershipLetterService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MembershipLetterNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public User $user) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $service = app(MembershipLetterService::class);
        $user = $this->user->loadMissing(['organization', 'branch']);
        $org = $user->organization;
        $settings = AppSetting::query()->first();

        $logoUrl = $org?->logo_path
            ? url($org->logo_path)
            : url($settings?->system_logo_path ?? '/images/logomywaphorizontal.png');

        return (new MailMessage)
            ->subject($service->subject($user))
            ->view('emails.membership-letter', [
                'name' => $user->name,
                'memberNo' => $user->member_no,
                'organization' => $org?->name,
                'logoUrl' => $logoUrl,
                'appName' => $settings?->app_name ?: config('app.name'),
            ])
            ->attachData($service->pdf($user)->output(), $service->filename($user), [
                'mime' => 'application/pdf',
            ]);
    }
}
