<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class NewOrderAdminNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly int $orderId,
        public readonly string $buyerName,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Pesanan mall baharu',
            'content' => "{$this->buyerName} telah membuat pesanan #{$this->orderId}.",
            'action_type' => 'order',
            'action_id' => $this->orderId,
            'action_label' => 'Lihat pesanan',
            'action_url' => route('orders.show', $this->orderId),
        ];
    }
}
