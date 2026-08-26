<?php

namespace App\Services;

use App\Models\DeviceToken;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * PushNotificationService
 *
 * Pendaftaran peranti + penghantaran push (FCM legacy HTTP API) untuk
 * Flutter mobile. Semua panggilan HTTP di-guard oleh config
 * `services.fcm.server_key` — tanpa key ia jadi no-op (log sahaja)
 * supaya test suite kekal offline.
 */
class PushNotificationService
{
    private const BATCH_SIZE = 500;

    private const FCM_ENDPOINT = 'https://fcm.googleapis.com/fcm/send';

    /**
     * Daftar token peranti untuk seorang user (upsert). Had maksimum
     * 5 token setiap user — token paling lama dibuang melebihi had.
     */
    public function register(User $user, string $token, string $platform, ?string $deviceName = null): bool
    {
        DeviceToken::updateOrCreate(
            ['token' => $token],
            ['user_id' => $user->id, 'platform' => $platform, 'device_name' => $deviceName]
        );

        $tokenIds = DeviceToken::where('user_id', $user->id)
            ->orderByDesc('id')
            ->pluck('id');

        $extra = $tokenIds->slice(5);

        if ($extra->isNotEmpty()) {
            DeviceToken::whereIn('id', $extra)->delete();
        }

        return true;
    }

    /**
     * Buang token peranti untuk seorang user.
     */
    public function unregister(User $user, string $token): void
    {
        DeviceToken::where('user_id', $user->id)->where('token', $token)->delete();
    }

    /**
     * Hantar push kepada semua token peranti untuk satu senarai user
     * (terima User model ataupun id). Query token di-chunk oleh sendToTokens.
     */
    public function sendToUsers(iterable $users, string $title, string $body, array $data = []): void
    {
        $ids = collect($users)
            ->map(fn ($u) => $u instanceof User ? $u->getKey() : $u)
            ->filter()
            ->values();

        if ($ids->isEmpty()) {
            return;
        }

        $tokens = DeviceToken::whereIn('user_id', $ids->all())
            ->pluck('token')
            ->all();

        $this->sendToTokens($tokens, $title, $body, $data);
    }

    public function sendToUser(User $user, string $title, string $body, array $data = []): void
    {
        $this->sendToUsers([$user], $title, $body, $data);
    }

    /**
     * Hantar push kepada semua token peranti user dalam satu organisasi.
     */
    public function sendToOrganization(int $organizationId, string $title, string $body, array $data = []): void
    {
        $ids = User::withoutGlobalScopes()
            ->where('current_organization_id', $organizationId)
            ->pluck('id')
            ->all();

        $this->sendToUsers($ids, $title, $body, $data);
    }

    /**
     * Hantar push sebenar melalui FCM legacy HTTP API, batch 500 token.
     * No-op (log info) bila `services.fcm.server_key` kosong.
     */
    public function sendToTokens(array $tokens, string $title, string $body, array $data = []): void
    {
        $serverKey = (string) config('services.fcm.server_key');

        if ($serverKey === '') {
            Log::info('PushNotificationService: FCM server key not configured, skipping send.', [
                'token_count' => count($tokens),
                'title' => $title,
            ]);

            return;
        }

        $payload = [
            'notification' => [
                'title' => $title,
                'body' => $body,
            ],
            'data' => collect($data)
                ->map(fn ($value) => is_scalar($value) ? (string) $value : $value)
                ->all(),
        ];

        foreach (array_chunk($tokens, self::BATCH_SIZE) as $chunk) {
            try {
                Http::withHeaders([
                    'Authorization' => 'key='.$serverKey,
                    'Content-Type' => 'application/json',
                ])->post(self::FCM_ENDPOINT, $payload + ['registration_ids' => $chunk]);
            } catch (\Throwable $e) {
                Log::error('PushNotificationService: FCM send failed.', [
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
