<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class FeeReminderNotification extends Notification
{
    use Queueable;

    public function __construct(
        public int $year,
        public float $amount,
        public string $organizationName,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => "Yuran Keahlian {$this->year}",
            'content' => "Yuran tahunan {$this->year} untuk {$this->organizationName}: RM "
                . number_format($this->amount, 2)
                . ". Sila lengkapkan pembayaran anda.",
            'amount' => $this->amount,
            'fee_year' => $this->year,
            'action_url' => route('member.financial.overview'),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Yuran Keahlian {$this->year}")
            ->greeting("Salam {$notifiable->name},")
            ->line("Yuran tahunan {$this->year} untuk {$this->organizationName} ialah RM "
                . number_format($this->amount, 2) . '.')
            ->line('Sila lengkapkan pembayaran yuran anda sebelum tarikh yang ditetapkan.')
            ->action('Bayar Sekarang', route('member.financial.overview'))
            ->line('Sekian, terima kasih.');
    }
}
