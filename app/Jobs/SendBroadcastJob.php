<?php

namespace App\Jobs;

use App\Models\BroadcastLog;
use App\Models\BroadcastMessage;
use App\Models\User;
use App\Notifications\GeneralBroadcastNotification;
use App\Services\PushNotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendBroadcastJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(public int $broadcastMessageId) {}

    public function handle(): void
    {
        $message = BroadcastMessage::withoutGlobalScope(\App\Models\Scopes\OrganizationScope::class)->find($this->broadcastMessageId);

        if (! $message || $message->sent_at) {
            return;
        }

        $channels = $message->notification_channels ?? ['in_app'];
        $wantInApp = in_array('in_app', $channels, true);
        $wantEmail = in_array('email', $channels, true);

        if (! $wantInApp && ! $wantEmail) {
            $message->update([
                'status' => BroadcastMessage::STATUS_FAILED,
                'error_message' => 'Tiada saluran penghantaran dipilih.',
                'finished_at' => now(),
            ]);
            $this->log($message, 'failed', null, null, 'Tiada saluran penghantaran dipilih.');

            return;
        }

        $message->update([
            'status' => BroadcastMessage::STATUS_PROCESSING,
            'started_at' => now(),
            'sent_at' => null,
            'error_message' => null,
        ]);
        $this->log($message, 'processing', null, null, 'Siaran mula diproses.');

        try {
            $query = User::withoutGlobalScope(\App\Models\Scopes\OrganizationScope::class);

            if ($message->target_criteria === 'all' && $message->target_organization_id) {
                $query->where('current_organization_id', $message->target_organization_id);
            }

            if ($message->target_criteria === 'organization') {
                $query->where('current_organization_id', $message->target_organization_id);
            }

            if ($message->target_criteria === 'branch') {
                $query->where('branch_id', $message->branch_id);
            }

            if ($message->target_criteria === 'specific_members') {
                $query->whereIn('id', $message->recipient_ids ?? []);
            }

            $target = 0;
            $success = 0;
            $failed = 0;
            $skipped = 0;
            $inAppUserIds = [];

            $query->orderBy('id')->chunk(200, function ($users) use ($message, $wantInApp, $wantEmail, &$target, &$success, &$failed, &$skipped, &$inAppUserIds) {
                foreach ($users as $user) {
                    $hasDatabase = $wantInApp;
                    $hasMail = $wantEmail && filled($user->email);

                    if (! $hasDatabase && ! $hasMail) {
                        $skipped++;

                        continue;
                    }

                    $target++;

                    if ($hasDatabase) {
                        $inAppUserIds[] = $user->id;
                    }

                    try {
                        $user->notify(new GeneralBroadcastNotification($message));
                        $success++;
                    } catch (Throwable $e) {
                        $failed++;
                        $this->log($message, 'delivery_failed', $wantEmail ? 'email' : null, $user->id, $e->getMessage());
                        Log::warning('SendBroadcastJob: notifikasi gagal untuk user.', [
                            'broadcast_message_id' => $message->id,
                            'user_id' => $user->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            });

            // Push FCM (banner peranti) hanya bila saluran In-App dipilih.
            if ($wantInApp && $inAppUserIds !== []) {
                $tokenCount = app(PushNotificationService::class)->sendToUsers(
                    $inAppUserIds,
                    $message->title,
                    $message->content,
                    ['type' => 'broadcast', 'broadcast_message_id' => $message->id]
                );

                $this->log($message, 'fcm_sent', 'in_app', null, "{$tokenCount} token peranti berjaya dihantar (FCM).");
            } elseif ($wantInApp) {
                $this->log($message, 'fcm_skipped', 'in_app', null, 'Tiada token peranti didaftarkan.');
            }

            $status = match (true) {
                $target > 0 && $success === 0 && $failed > 0 => BroadcastMessage::STATUS_FAILED,
                $failed > 0 => BroadcastMessage::STATUS_PARTIAL,
                default => BroadcastMessage::STATUS_COMPLETED,
            };

            $message->update([
                'status' => $status,
                'recipient_count' => $target,
                'success_count' => $success,
                'failed_count' => $failed,
                'finished_at' => now(),
                'sent_at' => $status === BroadcastMessage::STATUS_FAILED ? null : now(),
                'error_message' => $failed > 0 ? "{$failed} penerima gagal dihantar." : null,
            ]);

            $this->log(
                $message,
                $status,
                null,
                null,
                "Selesai: {$success} berjaya, {$failed} gagal, {$skipped} dilangkau."
            );
        } catch (Throwable $e) {
            Log::error('SendBroadcastJob: siaran gagal.', [
                'broadcast_message_id' => $message->id,
                'error' => $e->getMessage(),
            ]);

            $message->update([
                'status' => BroadcastMessage::STATUS_FAILED,
                'error_message' => $e->getMessage(),
                'finished_at' => now(),
            ]);
            $this->log($message, 'failed', null, null, $e->getMessage());

            throw $e;
        }
    }

    private function log(BroadcastMessage $message, string $event, ?string $channel, ?int $userId, ?string $text): void
    {
        try {
            BroadcastLog::create([
                'broadcast_message_id' => $message->id,
                'event' => $event,
                'channel' => $channel,
                'user_id' => $userId,
                'message' => $text,
            ]);
        } catch (Throwable $e) {
            Log::error('SendBroadcastJob: gagal menulis broadcast log.', [
                'broadcast_message_id' => $message->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
