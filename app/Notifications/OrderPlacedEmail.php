<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Pengesahan pesanan selepas bayaran berjaya, dihantar ke emel pelanggan.
 * Pautan resit ialah signed URL supaya tidak boleh dienumerasi.
 */
class OrderPlacedEmail extends Notification
{
    use Queueable;

    public function __construct(public Order $order) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $order = $this->order->loadMissing('items.product');
        $total = number_format((float) $order->total + (float) $order->postage_cost, 2, '.', '');

        $message = (new MailMessage)
            ->subject('Bayaran Disahkan, Pesanan Diproses #'.$order->id)
            ->greeting('Terima kasih')
            ->line('Bayaran anda telah disahkan. Pesanan sedang diproses.')
            ->line('Jumlah: RM '.$total);

        foreach ($order->items as $item) {
            $name = $item->product?->name ?? 'Item';
            $message->line("- {$name} x{$item->quantity}: RM ".number_format((float) $item->price * (int) $item->quantity, 2, '.', ''));
        }

        return $message
            ->action('Lihat Resit Pesanan', $order->publicUrl())
            ->line('Simpan pautan resit ini untuk menyemak status pesanan anda.');
    }
}
