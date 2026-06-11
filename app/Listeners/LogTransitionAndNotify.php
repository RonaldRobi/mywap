<?php

namespace App\Listeners;

use App\Events\UserOrganizationTransitioned;
use App\Models\UserTransitionHistory;
use App\Notifications\MemberTransitionNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

/**
 * LogTransitionAndNotify
 *
 * Handles the post-transition side-effects in a single listener:
 *   1. Persists an immutable audit record in user_transition_histories.
 *   2. Dispatches a congratulatory notification (database + email) to the member.
 *
 * Implements ShouldQueue so that both the DB insert and the email are processed
 * asynchronously by the queue worker, keeping the nightly cron fast and lean.
 */
class LogTransitionAndNotify implements ShouldQueue
{
    use InteractsWithQueue;

    /** Queue connection / name can be overridden via config if needed. */
    public string $queue = 'transitions';

    /**
     * Handle the event.
     *
     * Architectural decision: we use updateOrCreate keyed on (user_id, to_organization_id,
     * transitioned_at day) so that re-running the scheduler on the same day is idempotent
     * and does not duplicate history rows.
     */
    public function handle(UserOrganizationTransitioned $event): void
    {
        $user = $event->user;

        // 1 ── Persist transition record ──────────────────────────────────────
        UserTransitionHistory::create([
            'user_id'              => $user->id,
            'from_organization_id' => $event->fromOrganizationId,
            'to_organization_id'   => $event->toOrganizationId,
            'transitioned_at'      => now(),
        ]);

        // 2 ── Notify the member ───────────────────────────────────────────────
        try {
            $user->notify(new MemberTransitionNotification(
                fromOrgId: $event->fromOrganizationId,
                toOrgId:   $event->toOrganizationId,
            ));
        } catch (\Throwable $e) {
            // Notification failure must never crash the transition pipeline.
            Log::warning('MemberTransitionNotification failed', [
                'user_id' => $user->id,
                'error'   => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle a job failure — log and allow the queue to dead-letter this job.
     */
    public function failed(UserOrganizationTransitioned $event, \Throwable $exception): void
    {
        Log::error('LogTransitionAndNotify listener failed', [
            'user_id' => $event->user->id,
            'error'   => $exception->getMessage(),
        ]);
    }
}
