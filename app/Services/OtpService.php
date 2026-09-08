<?php

namespace App\Services;

use App\Models\OtpCode;
use App\Models\User;
use App\Notifications\OtpEmail;
use Carbon\Carbon;

class OtpService
{
    /**
     * Bilangan maksimum tekaan salah sebelum OTP dibatalkan (mengelak brute force).
     */
    public const MAX_ATTEMPTS = 5;

    public function generate(User $user, string $purpose = 'login'): OtpCode
    {
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

    public function send(User $user, string $purpose = 'login'): OtpCode
    {
        $otp = $this->generate($user, $purpose);

        $user->notify(new OtpEmail($otp->code, $purpose));

        return $otp;
    }

    public function verify(User $user, string $code, string $purpose = 'login'): bool
    {
        $otp = OtpCode::query()
            ->where('user_id', $user->id)
            ->where('purpose', $purpose)
            ->valid()
            ->latest()
            ->first();

        if (! $otp) {
            return false;
        }

        if (! hash_equals((string) $otp->code, (string) $code)) {
            $otp->increment('attempts');

            // Selepas cukup banyak tekaan salah, bakar kod itu supaya percubaan
            // selanjutnya tidak boleh diteruskan sehingga OTP baharu dihantar.
            if ($otp->attempts >= self::MAX_ATTEMPTS) {
                $otp->update(['used_at' => now()]);
            }

            return false;
        }

        $otp->update(['used_at' => now()]);

        return true;
    }

    public function invalidatePrevious(User $user, string $purpose = 'login'): void
    {
        OtpCode::query()
            ->where('user_id', $user->id)
            ->where('purpose', $purpose)
            ->whereNull('used_at')
            ->update(['used_at' => now()]);
    }

    private function generateCode(): string
    {
        return (string) random_int(100000, 999999);
    }
}
