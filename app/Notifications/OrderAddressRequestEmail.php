<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrderAddressRequestEmail extends Notification
{
    use Queueable;

    public function __construct(
        public readonly Order $order,
        public readonly string $addressUrl,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Lengkapkan alamat penghantaran pesanan #'.$this->order->id)
            ->greeting('Salam '.($this->order->shipping_name ?: 'pelanggan'))
            ->line('Sila lengkapkan alamat penghantaran untuk pesanan MyWAP anda.')
            ->line('Maklumat ini diperlukan supaya pihak kami boleh menghantar pesanan anda.')
            ->action('Kemaskini Alamat Penghantaran', $this->addressUrl)
            ->line('Pautan ini hanya untuk pesanan anda dan akan tamat tempoh dalam 7 hari.');
    }
}
