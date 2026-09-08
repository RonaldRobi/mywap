<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * FacilityBookingNotification
 *
 * Notifikasi dalam-app (lonceng) untuk sebarang kemaskini tempahan fasiliti —
 * tempahan baharu (kepada admin), kelulusan/penolakan & bayaran (kepada
 * penempah). Push FCM dihantar secara berasingan oleh
 * App\Services\FacilityBookingNotifier menggunakan data yang sama.
 *
 * Saluran 'database' sahaja supaya proses tempahan tidak bergantung pada kuota
 * emel transaksi.
 */
class FacilityBookingNotification extends Notification
{
    use Queueable;

    /**
     * @param  array<string, mixed>  $data  Data tambahan (booking_id, facility_name, event, ...).
     */
    public function __construct(
        public readonly string $title,
        public readonly string $content,
        public readonly array $data = [],
    ) {}

    /**
     * Deliver via database (in-app bell) only.
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Database (in-app) representation — keys mesti selari dengan model Flutter
     * `AppNotification` (title, content, type, ...).
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return array_merge($this->data, [
            'title' => $this->title,
            'content' => $this->content,
        ]);
    }
}
