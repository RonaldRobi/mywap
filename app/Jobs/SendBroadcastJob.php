<?php

namespace App\Jobs;

use App\Models\BroadcastMessage;
use App\Models\User;
use App\Notifications\GeneralBroadcastNotification;
use App\Services\PushNotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendBroadcastJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $broadcastMessageId) {}

    public function handle(): void
    {
        $message = BroadcastMessage::withoutGlobalScopes()->find($this->broadcastMessageId);

        if (! $message || $message->sent_at) {
            return;
        }

        $query = User::withoutGlobalScopes();

        if ($message->target_criteria === 'organization') {
            $query->where('current_organization_id', $message->target_organization_id);
        }

        if ($message->target_criteria === 'branch') {
            $query->where('branch_id', $message->branch_id);
        }

        if ($message->target_criteria === 'specific_members') {
            $query->whereIn('id', $message->recipient_ids ?? []);
        }

        $userIds = [];

        $query->orderBy('id')->chunk(200, function ($users) use ($message, &$userIds) {
            foreach ($users as $user) {
                $userIds[] = $user->id;
                $user->notify(new GeneralBroadcastNotification($message));
            }
        });

        app(PushNotificationService::class)->sendToUsers(
            $userIds,
            $message->title,
            $message->content,
            ['type' => 'broadcast', 'broadcast_message_id' => $message->id]
        );

        $message->update(['sent_at' => now()]);
    }
}
