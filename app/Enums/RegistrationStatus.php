<?php

namespace App\Enums;

enum RegistrationStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Cancelled = 'cancelled';

    /**
     * Kehadiran walk-in — dicipta automatik apabila ahli mengimbas QR
     * kehadiran program tanpa pendaftaran terdahulu. Bukan pendaftaran
     * rasmi: tiada borang, tiada bayaran.
     */
    case WalkIn = 'walkin';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Menunggu Bayaran',
            self::Confirmed => 'Diterima',
            self::Cancelled => 'Dibatalkan',
            self::WalkIn => 'Walk-in',
        };
    }
}
