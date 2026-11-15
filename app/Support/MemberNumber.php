<?php

namespace App\Support;

use App\Models\Organization;
use App\Models\User;

/**
 * Penjana no. ahli berurutan mengikut organisasi.
 *
 * Setiap organisasi ada siri sendiri: PKPIM (P), ABIM (A), WADAH (W).
 * No ahli lama tidak di-stack; ia disimpan sebagai sejarah peralihan.
 */
class MemberNumber
{
    public static function prefixFor(Organization|string|null $organization): ?string
    {
        $slug = $organization instanceof Organization ? $organization->slug : $organization;

        return match ($slug) {
            'pkpim' => 'P',
            'abim' => 'A',
            'wadah' => 'W',
            default => null,
        };
    }

    /**
     * @return array{member_no: string, sequence: int}|null
     */
    public static function allocate(Organization $organization): ?array
    {
        $prefix = self::prefixFor($organization);

        if (! $prefix) {
            return null;
        }

        $padding = $prefix === 'W' ? 4 : 5;

        $max = User::withoutGlobalScopes()
            ->where('member_no', 'like', $prefix.'%')
            ->max('member_no_sequence');

        $sequence = ((int) $max) + 1;

        return [
            'member_no' => $prefix.str_pad((string) $sequence, $padding, '0', STR_PAD_LEFT),
            'sequence' => $sequence,
        ];
    }
}
