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
     * Tanda semua notifikasi belum dibaca sebagai telah dibaca.
     */
    public function markAllRead(User $user): void
    {
        $user->unreadNotifications->markAsRead();
    }
}
