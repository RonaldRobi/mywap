<?php

namespace App\Notifications;

use App\Models\BroadcastMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Siaran emel kepada Pendukung (bukan ahli). Tidak bergantung pada medan
 * `email`/`name` pada notifiable kerana penerima ialah AnonymousNotifiable
 * (dihantar melalui Notification::route('mail', ...)).
 */
class SupporterBroadcastNotification extends Notification
{
    use Queueable;

    public function __construct(public BroadcastMessage $broadcastMessage) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)->mailer('bulk');

        $message->subject($this->broadcastMessage->title);

        if ($this->broadcastMessage->email_use_template) {
            return $message->markdown('emails.broadcast-push', [
                'title' => $this->broadcastMessage->title,
                'content' => $this->broadcastMessage->content,
                'name' => 'Pendukung',
            ]);
        }

        $message->line($this->broadcastMessage->content);

        if (filled($this->broadcastMessage->action_url)) {
            $message->action(
                $this->broadcastMessage->action_label ?: 'Buka Pautan',
                $this->broadcastMessage->action_url,
            );
        }

        return $message;
    }
}
