<?php

namespace App\Services;

use App\Models\User;

class NotificationService
{
    /**
     * Senarai 20 notifikasi terbaru user.
     */
    public function notifications(User $user): array
    {
        return $user->notifications()
            ->take(20)
            ->get()
            ->map(fn ($n) => [
                'id' => $n->id,
                'type' => $n->type,
                'data' => $n->data,
                'read_at' => $n->read_at?->toISOString(),
                'created_at' => $n->created_at->toISOString(),
            ])
            ->all();
    }

    /**
     * Tanda satu notifikasi milik user sebagai telah dibaca.
     *
     * Pulangkan `false` bila notifikasi tiada atau bukan milik user supaya
     * controller boleh balas 404 tanpa membocorkan notifikasi user lain.
     */
    public function markRead(User $user, string $notificationId): bool
    {
        $notification = $user->notifications()
            ->whereKey($notificationId)
            ->first();

        if ($notification === null) {
            return false;
        }

        if ($notification->read_at === null) {
            $notification->markAsRead();
        }

        return true;
    }

    /**
     * Tanda semua notifikasi belum dibaca sebagai telah dibaca.
     */
    public function markAllRead(User $user): void
    {
        $user->unreadNotifications->markAsRead();
    }
}
