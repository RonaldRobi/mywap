<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * MemberTransitionNotification
 *
 * Sent to a member after the Age Transition Engine migrates them to a new NGO tier.
 * Delivered via both database (in-app notification bell) and email.
 *
 * The notification is queued (via ShouldQueue on the listener) so it does not
 * block the nightly cron.
 */
class MemberTransitionNotification extends Notification
{
    use Queueable;

    /**
     * @param  int|null  $fromOrgId  Previous organization ID (null = first join).
     * @param  int       $toOrgId    New organization ID after transition.
     */
    public function __construct(
        public readonly ?int $fromOrgId,
        public readonly int  $toOrgId,
    ) {}

    /**
     * Deliver via database (for in-app bell) AND email.
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    /**
     * Email representation.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $toOrg   = \App\Models\Organization::find($this->toOrgId);
        $fromOrg = $this->fromOrgId ? \App\Models\Organization::find($this->fromOrgId) : null;

        $greeting = $fromOrg
            ? "Tahniah! Anda telah berjaya beralih dari {$fromOrg->name} ke {$toOrg->name}."
            : "Selamat datang ke {$toOrg->name}! Perjalanan keahlian anda bermula hari ini.";

        return (new MailMessage)
            ->subject("MyMarhalah — Selamat datang ke {$toOrg->name}!")
            ->greeting("Salam, {$notifiable->name}!")
            ->line($greeting)
            ->line('Akun dan semua rekod anda telah dipindahkan secara automatik. Tiada tindakan diperlukan daripada anda.')
            ->action('Lihat Profil Anda', route('profile.show'))
            ->line('Terima kasih kerana bersama MyMarhalah.');
    }

    /**
     * Database (in-app) representation — stored in the notifications table.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $toOrg = \App\Models\Organization::find($this->toOrgId);

        return [
            'type'               => 'organization_transition',
            'from_organization_id' => $this->fromOrgId,
            'to_organization_id'   => $this->toOrgId,
            'to_organization_name' => $toOrg?->name,
            'message'            => "Anda kini ahli {$toOrg?->name}.",
        ];
    }
}
