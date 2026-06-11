<?php

namespace App\Notifications;

use App\Models\BroadcastMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class GeneralBroadcastNotification extends Notification
{
    use Queueable;

    public function __construct(public BroadcastMessage $broadcastMessage)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'broadcast_id' => $this->broadcastMessage->id,
            'title' => $this->broadcastMessage->title,
            'content' => $this->broadcastMessage->content,
            'target_criteria' => $this->broadcastMessage->target_criteria,
            'sent_at' => now()->toDateTimeString(),
        ];
    }
}
