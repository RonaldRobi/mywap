<?php

namespace App\Jobs;

use App\Models\BroadcastMessage;
use App\Models\User;
use App\Notifications\GeneralBroadcastNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendBroadcastJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $broadcastMessageId)
    {
    }

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

        if ($message->target_criteria === 'specific_members') {
            $query->whereIn('id', $message->recipient_ids ?? []);
        }

        $query->orderBy('id')->chunk(200, function ($users) use ($message) {
            foreach ($users as $user) {
                $user->notify(new GeneralBroadcastNotification($message));
            }
        });

        $message->update(['sent_at' => now()]);
    }
}
