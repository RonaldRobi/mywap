<?php

namespace App\Services;

use App\Models\DeviceToken;
use App\Models\User;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * PushNotificationService
 *
 * Pendaftaran peranti + penghantaran push (FCM) untuk Flutter mobile.
 *
 * Dua mod penghantaran (ikut config `services.fcm`):
 *   1. HTTP v1 (disarankan) — guna Service Account JSON + OAuth2 JWT,
 *      endpoint `fcm.googleapis.com/v1/projects/{id}/messages:send`.
 *      Set `FCM_SERVICE_ACCOUNT` = path ke fail JSON.
 *   2. Legacy HTTP API (fallback) — guna `FCM_SERVER_KEY` (Server key),
 *      endpoint `fcm.googleapis.com/fcm/send`. Fasa-out oleh Google.
 *
 * Tanpa kedua-duanya, ia jadi no-op (log sahaja) supaya test suite kekal offline.
 */
class PushNotificationService
{
    private const BATCH_SIZE = 500;

    private const FCM_V1_ENDPOINT = 'https://fcm.googleapis.com/v1/projects/%s/messages:send';

    private const FCM_LEGACY_ENDPOINT = 'https://fcm.googleapis.com/fcm/send';

    /** Token OAuth2 untuk FCM v1 dikira semula bila hampir tamat. */
    private ?string $accessToken = null;

    private ?int $accessTokenExpiresAt = 0;

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
     * Hantar push sebenar. Pilih FCM v1 (service account) dahulu, jatuh
     * kepada legacy HTTP API sebagai fallback. No-op bila kedua-dua kosong.
     */
    public function sendToTokens(array $tokens, string $title, string $body, array $data = []): void
    {
        if (empty($tokens)) {
            return;
        }

        $serviceAccountPath = (string) config('services.fcm.service_account', '');
        $serverKey = (string) config('services.fcm.server_key', '');

        if ($serviceAccountPath !== '' && is_file($serviceAccountPath)) {
            $this->sendViaHttpV1($tokens, $title, $body, $data, $serviceAccountPath);

            return;
        }

        if ($serverKey !== '') {
            $this->sendViaLegacy($tokens, $title, $body, $data, $serverKey);

            return;
        }

        Log::info('PushNotificationService: no FCM credential configured, skipping send.', [
            'token_count' => count($tokens),
            'title' => $title,
        ]);
    }

    /**
     * Hantar melalui FCM HTTP v1 (satu mesej setiap token). Service account
     * membekalkan OAuth2 token yang sah untuk menghantar push.
     */
    private function sendViaHttpV1(array $tokens, string $title, string $body, array $data, string $serviceAccountPath): void
    {
        $projectId = $this->serviceAccountProjectId($serviceAccountPath);
        if ($projectId === '') {
            Log::error('PushNotificationService: invalid service account file.', ['path' => $serviceAccountPath]);

            return;
        }

        $accessToken = $this->getAccessToken($serviceAccountPath);
        if ($accessToken === null) {
            Log::error('PushNotificationService: failed to obtain OAuth2 access token.');

            return;
        }

        // Normalize data values to string (FCM v1 memerlukan string).
        $data = collect($data)
            ->map(fn ($value) => is_scalar($value) ? (string) $value : $value)
            ->all();

        foreach ($tokens as $token) {
            $payload = [
                'message' => [
                    'token' => $token,
                    'notification' => [
                        'title' => $title,
                        'body' => $body,
                    ],
                    'data' => $data,
                ],
            ];

            try {
                $response = Http::withToken($accessToken)
                    ->post(sprintf(self::FCM_V1_ENDPOINT, $projectId), $payload);

                if ($response->failed()) {
                    Log::error('PushNotificationService: FCM v1 send failed.', [
                        'token' => substr($token, 0, 12).'...',
                        'status' => $response->status(),
                        'error' => $response->json('error.message') ?? $response->body(),
                    ]);
                }
            } catch (\Throwable $e) {
                Log::error('PushNotificationService: FCM v1 exception.', [
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Hantar melalui FCM legacy HTTP API (batch 500 token). Fallback sahaja.
     */
    private function sendViaLegacy(array $tokens, string $title, string $body, array $data, string $serverKey): void
    {
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
                ])->post(self::FCM_LEGACY_ENDPOINT, $payload + ['registration_ids' => $chunk]);
            } catch (\Throwable $e) {
                Log::error('PushNotificationService: FCM legacy send failed.', [
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Dapatkan project_id daripada fail service account JSON.
     */
    private function serviceAccountProjectId(string $path): string
    {
        return $this->parseServiceAccount($path)['project_id'] ?? '';
    }

    /**
     * Baca fail service account JSON. Top up token OAuth2 bila perlu.
     */
    private function parseServiceAccount(string $path): array
    {
        $raw = file_get_contents($path);
        if ($raw === false) {
            return [];
        }

        $json = json_decode($raw, true);

        return is_array($json) ? $json : [];
    }

    /**
     * Dapatkan (dan cache) OAuth2 access token untuk FCM v1.
     * Token = JWT yang ditandatangan dengan private key service account.
     */
    private function getAccessToken(string $serviceAccountPath): ?string
    {
        if ($this->accessToken !== null && $this->accessTokenExpiresAt > time() + 60) {
            return $this->accessToken;
        }

        $sa = $this->parseServiceAccount($serviceAccountPath);
        $clientEmail = $sa['client_email'] ?? '';
        $privateKey = $sa['private_key'] ?? '';

        if ($clientEmail === '' || $privateKey === '') {
            return null;
        }

        $now = time();
        $assertion = [
            'iss' => $clientEmail,
            'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
            'aud' => 'https://oauth2.googleapis.com/token',
            'iat' => $now,
            'exp' => $now + 3600,
        ];

        try {
            $jwt = JWT::encode($assertion, $privateKey, 'RS256');

            $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt,
            ]);

            if ($response->failed()) {
                Log::error('PushNotificationService: OAuth2 token request failed.', [
                    'error' => $response->json('error_description') ?? $response->body(),
                ]);

                return null;
            }

            $this->accessToken = $response->json('access_token');
            $this->accessTokenExpiresAt = $now + (int) $response->json('expires_in', 3600);

            return $this->accessToken;
        } catch (\Throwable $e) {
            Log::error('PushNotificationService: OAuth2 JWT mint failed.', [
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
