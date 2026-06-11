<?php

namespace App\Jobs;

use App\Models\BroadcastMessage;
use App\Models\Payment;
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

        $query = User::withoutGlobalScopes()
            ->where('current_organization_id', $message->organization_id);

        if ($message->target_criteria === 'unpaid_fees') {
            $query->whereDoesntHave('payments', function ($paymentQuery) {
                $paymentQuery->where('status', 'successful')
                    ->where('payable_type', 'membership_fee')
                    ->whereYear('created_at', now()->year);
            });
        }

        if ($message->target_criteria === 'specific_usrah' && $message->usrah_group_id) {
            $query->whereHas('usrahGroups', function ($usrahQuery) use ($message) {
                $usrahQuery->where('usrah_groups.id', $message->usrah_group_id);
            });
        }

        $query->orderBy('id')->chunk(200, function ($users) use ($message) {
            foreach ($users as $user) {
                $user->notify(new GeneralBroadcastNotification($message));
            }
        });

        $message->update(['sent_at' => now()]);
    }
}
