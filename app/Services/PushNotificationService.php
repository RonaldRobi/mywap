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
     * (terima User model ataupun id). Pulangkan bilangan token yang BERJAYA
     * diterima FCM (bukan bilangan token cubaan).
     */
    public function sendToUsers(iterable $users, string $title, string $body, array $data = []): int
    {
        $ids = collect($users)
            ->map(fn ($u) => $u instanceof User ? $u->getKey() : $u)
            ->filter()
            ->values();

        if ($ids->isEmpty()) {
            return 0;
        }

        $tokens = DeviceToken::whereIn('user_id', $ids->all())
            ->pluck('token')
            ->all();

        return $this->sendToTokens($tokens, $title, $body, $data);
    }

    public function sendToUser(User $user, string $title, string $body, array $data = []): int
    {
        return $this->sendToUsers([$user], $title, $body, $data);
    }

    /**
     * Hantar push kepada semua token peranti user dalam satu organisasi.
     */
    public function sendToOrganization(int $organizationId, string $title, string $body, array $data = []): int
    {
        $ids = User::withoutGlobalScopes()
            ->where('current_organization_id', $organizationId)
            ->pluck('id')
            ->all();

        return $this->sendToUsers($ids, $title, $body, $data);
    }

    /**
     * Hantar push sebenar. Pilih FCM v1 (service account) dahulu, jatuh
     * kepada legacy HTTP API sebagai fallback. No-op bila kedua-dua kosong.
     * Pulangkan bilangan token yang BERJAYA diterima FCM (bukan bilangan cubaan).
     */
    public function sendToTokens(array $tokens, string $title, string $body, array $data = []): int
    {
        if (empty($tokens)) {
            return 0;
        }

        $serviceAccountPath = (string) config('services.fcm.service_account', '');
        $serverKey = (string) config('services.fcm.server_key', '');

        if ($serviceAccountPath !== '') {
            // Path relatif (cth. `storage/firebase/...`) mesti diselesaikan
            // terhadap base_path() — bukan cwd — supaya ia berfungsi walau
            // proses queue/web dijalankan dari direktori berbeza.
            $serviceAccountPath = $this->resolveServiceAccountPath($serviceAccountPath);
        }

        if ($serviceAccountPath !== '' && is_file($serviceAccountPath)) {
            return $this->sendViaHttpV1($tokens, $title, $body, $data, $serviceAccountPath);
        }

        if ($serverKey !== '') {
            return $this->sendViaLegacy($tokens, $title, $body, $data, $serverKey);
        }

        Log::info('PushNotificationService: no FCM credential configured, skipping send.', [
            'token_count' => count($tokens),
            'title' => $title,
        ]);

        return 0;
    }

    /**
     * Tukar path service account relatif kepada path mutlak berasaskan
     * base_path(). Path mutlak (bermula `/`) dibiarkan seperti sedia ada.
     */
    private function resolveServiceAccountPath(string $path): string
    {
        if ($path === '' || str_starts_with($path, '/') || str_starts_with($path, '\\')) {
            return $path;
        }

        return base_path($path);
    }

    /**
     * Hantar melalui FCM HTTP v1 (satu mesej setiap token). Service account
     * membekalkan OAuth2 token yang sah untuk menghantar push.
     */
    private function sendViaHttpV1(array $tokens, string $title, string $body, array $data, string $serviceAccountPath): int
    {
        $projectId = $this->serviceAccountProjectId($serviceAccountPath);
        if ($projectId === '') {
            Log::error('PushNotificationService: invalid service account file.', ['path' => $serviceAccountPath]);

            return 0;
        }

        $accessToken = $this->getAccessToken($serviceAccountPath);
        if ($accessToken === null) {
            Log::error('PushNotificationService: failed to obtain OAuth2 access token.');

            return 0;
        }

        // Normalize data values to string (FCM v1 memerlukan string).
        $data = collect($data)
            ->map(fn ($value) => is_scalar($value) ? (string) $value : $value)
            ->all();

        $success = 0;

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
                    $errorCode = $response->json('error.details.0.errorCode')
                        ?? $response->json('error.status')
                        ?? null;

                    Log::error('PushNotificationService: FCM v1 send failed.', [
                        'token' => substr($token, 0, 12).'...',
                        'status' => $response->status(),
                        'error_code' => $errorCode,
                        'error' => $response->json('error.message') ?? $response->body(),
                    ]);

                    // Buang token yang sudah tidak sah (device uninstall / app
                    // data dikosongkan) supaya siaran seterusnya tidak cuba
                    // menghantar ke token mati berulang kali.
                    if (in_array($errorCode, ['UNREGISTERED', 'NOT_FOUND', 'INVALID_ARGUMENT'], true)) {
                        DeviceToken::where('token', $token)->delete();
                    }
                } else {
                    $success++;
                }
            } catch (\Throwable $e) {
                Log::error('PushNotificationService: FCM v1 exception.', [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $success;
    }

    /**
     * Hantar melalui FCM legacy HTTP API (batch 500 token). Fallback sahaja.
     */
    private function sendViaLegacy(array $tokens, string $title, string $body, array $data, string $serverKey): int
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

        $success = 0;

        foreach (array_chunk($tokens, self::BATCH_SIZE) as $chunk) {
            try {
                $response = Http::withHeaders([
                    'Authorization' => 'key='.$serverKey,
                    'Content-Type' => 'application/json',
                ])->post(self::FCM_LEGACY_ENDPOINT, $payload + ['registration_ids' => $chunk]);

                if ($response->failed()) {
                    Log::error('PushNotificationService: FCM legacy send failed.', [
                        'status' => $response->status(),
                        'error' => $response->body(),
                    ]);

                    continue;
                }

                $result = $response->json();
                $success += (int) ($result['success'] ?? 0);

                foreach (($result['results'] ?? []) as $index => $item) {
                    if (isset($item['error']) && in_array($item['error'], ['NotRegistered', 'InvalidRegistration'], true)) {
                        $token = $chunk[$index] ?? null;
                        if ($token) {
                            DeviceToken::where('token', $token)->delete();
                        }
                    }
                }
            } catch (\Throwable $e) {
                Log::error('PushNotificationService: FCM legacy send failed.', [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $success;
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
