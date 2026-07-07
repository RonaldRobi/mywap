<?php

namespace App\Notifications;

use App\Models\BroadcastMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class GeneralBroadcastNotification extends Notification
{
    use Queueable;

    public function __construct(public BroadcastMessage $broadcastMessage)
    {
    }

    public function via(object $notifiable): array
    {
        return $this->broadcastMessage->notification_channels ?? ['in_app'];
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

    public function toMail(object $notifiable): MailMessage
    {
        $message = new MailMessage();

        if ($this->broadcastMessage->email_use_template) {
            return $message
                ->subject($this->broadcastMessage->title)
                ->markdown('emails.broadcast-push', [
                    'title' => $this->broadcastMessage->title,
                    'content' => $this->broadcastMessage->content,
                    'name' => $notifiable->name,
                ]);
        }

        return $message
            ->subject($this->broadcastMessage->title)
            ->line($this->broadcastMessage->content);
    }
}
