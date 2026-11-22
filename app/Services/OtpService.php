<?php

namespace App\Services;

use App\Models\OtpCode;
use App\Models\User;
use App\Notifications\OtpEmail;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class OtpService
{
    /**
     * Bilangan maksimum tekaan salah sebelum OTP dibatalkan (mengelak brute force).
     */
    public const MAX_ATTEMPTS = 5;

    public const CHANNEL_COOLDOWN_SECONDS = 30;

    public const BOTH_CHANNEL_COOLDOWN_SECONDS = 60;

    public function generate(User $user, string $purpose = 'login', ?string $channel = null): OtpCode
    {
        $retryAfter = $this->retryAfter($user, $purpose, $channel);
        if ($retryAfter > 0) {
            throw ValidationException::withMessages([
                'otp' => 'Sila tunggu '.$this->formatRetryAfter($retryAfter).' sebelum meminta kod OTP baharu.',
            ]);
        }

        $this->invalidatePrevious($user, $purpose);

        $code = $this->generateCode();

        $otp = OtpCode::create([
            'user_id' => $user->id,
            'code' => $code,
            'purpose' => $purpose,
            'expires_at' => Carbon::now()->addMinutes(5),
            'attempts' => 0,
        ]);

        return $otp;
    }

    public function send(User $user, string $purpose = 'login', string $channel = 'email', ?string $recipient = null): OtpCode
    {
        $otp = $this->generate($user, $purpose, $channel);
        $recipient ??= $channel === 'whatsapp' ? (string) $user->phone : (string) $user->email;
        $otp->update(['channel' => $channel, 'recipient' => $recipient]);

        if ($channel === 'whatsapp') {
            if (! app(OneSenderWhatsappService::class)->sendOtp($recipient, $otp->code)) {
                $otp->update(['used_at' => now()]);
                throw ValidationException::withMessages([
                    'otp' => 'Kod WhatsApp tidak dapat dihantar. Sila cuba lagi atau pilih emel.',
                ]);
            }
        } else {
            try {
                $user->notify(new OtpEmail($otp->code, $purpose, $recipient));
            } catch (\Throwable $exception) {
                $otp->update(['used_at' => now()]);
                Log::error('OTP email delivery failed.', [
                    'user_id' => $user->id,
                    'purpose' => $purpose,
                    'error' => $exception->getMessage(),
                ]);

                throw ValidationException::withMessages([
                    'otp' => 'OTP emel tidak dapat dihantar. Sila cuba lagi atau pilih WhatsApp.',
                ]);
            }
        }

        return $otp;
    }

    public function verify(User $user, string $code, string $purpose = 'login'): bool
    {
        return $this->verifyAndReturn($user, $code, $purpose) !== null;
    }

    public function verifyAndReturn(User $user, string $code, string $purpose = 'login'): ?OtpCode
    {
        $otp = OtpCode::query()
            ->where('user_id', $user->id)
            ->where('purpose', $purpose)
            ->valid()
            ->latest()
            ->first();

        if (! $otp) {
            return null;
        }

        if (! hash_equals((string) $otp->code, (string) $code)) {
            $otp->increment('attempts');

            // Selepas cukup banyak tekaan salah, bakar kod itu supaya percubaan
            // selanjutnya tidak boleh diteruskan sehingga OTP baharu dihantar.
            if ($otp->attempts >= self::MAX_ATTEMPTS) {
                $otp->update(['used_at' => now()]);
            }

            return null;
        }

        $otp->update(['used_at' => now()]);

        return $otp;
    }

    public function invalidatePrevious(User $user, string $purpose = 'login'): void
    {
        OtpCode::query()
            ->where('user_id', $user->id)
            ->where('purpose', $purpose)
            ->whereNull('used_at')
            ->update(['used_at' => now()]);
    }

    public function retryAfter(User $user, string $purpose = 'login', ?string $channel = null): int
    {
        $recent = OtpCode::query()
            ->where('user_id', $user->id)
            ->where('purpose', $purpose)
            ->latest()
            ->limit(2)
            ->get();

        $latest = $recent->first();
        if (! $latest || $latest->created_at === null) {
            return 0;
        }

        $elapsed = (int) ceil($latest->created_at->diffInRealSeconds(now(), false));

        if ($elapsed < 0) {
            return self::BOTH_CHANNEL_COOLDOWN_SECONDS;
        }

        if ($channel !== null && $latest->channel === $channel) {
            return max(0, self::CHANNEL_COOLDOWN_SECONDS - $elapsed);
        }

        if ($recent->count() >= 2
            && $recent[0]->channel !== null
            && $recent[1]->channel !== null
            && $recent[0]->channel !== $recent[1]->channel) {
            return max(0, self::BOTH_CHANNEL_COOLDOWN_SECONDS - $elapsed);
        }

        if ($channel === null) {
            return max(0, self::CHANNEL_COOLDOWN_SECONDS - $elapsed);
        }

        $latestForChannel = $recent->firstWhere('channel', $channel);
        if (! $latestForChannel || $latestForChannel->created_at === null) {
            return max(0, self::CHANNEL_COOLDOWN_SECONDS - $elapsed);
        }

        $channelElapsed = (int) ceil($latestForChannel->created_at->diffInRealSeconds(now(), false));

        return $channelElapsed < 0
            ? self::CHANNEL_COOLDOWN_SECONDS
            : max(0, self::CHANNEL_COOLDOWN_SECONDS - $channelElapsed);
    }

    public function cooldownAfterSend(User $user, string $purpose = 'login'): int
    {
        $recent = OtpCode::query()
            ->where('user_id', $user->id)
            ->where('purpose', $purpose)
            ->latest()
            ->limit(2)
            ->get();

        if ($recent->count() >= 2
            && $recent[0]->channel !== null
            && $recent[1]->channel !== null
            && $recent[0]->channel !== $recent[1]->channel) {
            return self::BOTH_CHANNEL_COOLDOWN_SECONDS;
        }

        return self::CHANNEL_COOLDOWN_SECONDS;
    }

    private function generateCode(): string
    {
        return (string) random_int(100000, 999999);
    }

    private function formatRetryAfter(int $seconds): string
    {
        $minutes = intdiv($seconds, 60);
        $remainingSeconds = $seconds % 60;

        if ($minutes === 0) {
            return "{$remainingSeconds} saat";
        }

        if ($remainingSeconds === 0) {
            return $minutes === 1 ? '1 minit' : "{$minutes} minit";
        }

        return ($minutes === 1 ? '1 minit' : "{$minutes} minit")." {$remainingSeconds} saat";
    }
}
