<?php

namespace App\Notifications;

use App\Models\Organization;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * MemberTransitionNotification
 *
 * Sent to a member after the Age Transition Engine migrates them to a new NGO tier.
 *
 * Delivered via database (in-app bell) ONLY. Email is intentionally disabled so that
 * bulk transition jobs never consume the daily email quota (Resend free = 100/day)
 * which must be reserved for essential transactional mail (OTP verification codes,
 * password reset, registration confirmations).
 *
 * To re-enable email delivery (e.g. after upgrading the mail plan), add 'mail' to
 * the `via()` array and restore the `toMail()` method.
 */
class MemberTransitionNotification extends Notification
{
    use Queueable;

    /**
     * @param  int|null  $fromOrgId  Previous organization ID (null = first join).
     * @param  int  $toOrgId  New organization ID after transition.
     */
    public function __construct(
        public readonly ?int $fromOrgId,
        public readonly int $toOrgId,
    ) {}

    /**
     * Deliver via database (in-app bell) only — protects the email quota.
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Database (in-app) representation — stored in the notifications table.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $toOrg = Organization::find($this->toOrgId);

        return [
            'type' => 'organization_transition',
            'from_organization_id' => $this->fromOrgId,
            'to_organization_id' => $this->toOrgId,
            'to_organization_name' => $toOrg?->name,
            'message' => "Anda kini ahli {$toOrg?->name}.",
        ];
    }
}
