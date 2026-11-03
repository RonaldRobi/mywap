<?php

namespace App\Notifications;

use App\Models\BroadcastMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class GeneralBroadcastNotification extends Notification
{
    use Queueable;

    public function __construct(public BroadcastMessage $broadcastMessage) {}

    /**
     * Saluran dipilih di admin disimpan sebagai nilai UI ('in_app', 'email'),
     * manakala Laravel cuma kenal channel ('database', 'mail', dll).
     * Peta di sini supaya notifikasi tidak gagal dengan "Driver not supported".
     *
     * Emel hanya dihantar kepada ahli yang ada alamat emel — ahli tanpa emel
     * tidak boleh menerima saluran 'email' (elak ralat MailChannel).
     */
    public function via(object $notifiable): array
    {
        $channels = $this->broadcastMessage->notification_channels ?? ['in_app'];

        $map = [
            'in_app' => 'database',
            'email' => 'mail',
        ];

        $canEmail = filled($notifiable->email ?? null);

        return collect($channels)
            ->map(function (string $channel) use ($canEmail, $map) {
                if ($channel === 'email' && ! $canEmail) {
                    return null;
                }

                return $map[$channel] ?? null;
            })
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'broadcast',
            'broadcast_id' => $this->broadcastMessage->id,
            'title' => $this->broadcastMessage->title,
            'content' => $this->broadcastMessage->content,
            'target_criteria' => $this->broadcastMessage->target_criteria,
            'action_type' => $this->broadcastMessage->action_type,
            'action_id' => $this->broadcastMessage->action_id,
            'action_label' => $this->broadcastMessage->action_label,
            'action_url' => $this->broadcastMessage->action_url,
            'sent_at' => now()->toDateTimeString(),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = new MailMessage;

        if ($this->broadcastMessage->email_use_template) {
            return $message
                ->subject($this->broadcastMessage->title)
                ->markdown('emails.broadcast-push', [
                    'title' => $this->broadcastMessage->title,
                    'content' => $this->broadcastMessage->content,
                    'name' => $notifiable->name,
                ]);
        }

        $message
            ->subject($this->broadcastMessage->title)
            ->line($this->broadcastMessage->content);

        if (filled($this->broadcastMessage->action_url)) {
            $message->action(
                $this->broadcastMessage->action_label ?: 'Buka Pautan',
                $this->broadcastMessage->action_url,
            );
        }

        return $message;
    }
}
