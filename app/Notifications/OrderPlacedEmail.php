<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Resit / pengesahan pesanan diterima, dihantar ke emel pelanggan (ahli atau
 * tetamu). Pautan resit ialah signed URL supaya tidak boleh dienumerasi.
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
            ->subject('Pesanan Diterima #'.$order->id)
            ->greeting('Terima kasih')
            ->line('Pesanan anda telah diterima dan sedang diproses.')
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
