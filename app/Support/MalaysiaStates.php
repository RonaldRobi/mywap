<?php

namespace App\Support;

/**
 * Senarai negeri/Wilayah Persekutuan Malaysia yang digunakan untuk dropdown
 * filter dan pengisian borang. Dikongsi supaya semua skrin (ahli, cawangan,
 * export) guna set yang sama dan tidak tertinggal negeri.
 */
class MalaysiaStates
{
    /** 13 negeri + Kuala Lumpur = 14 pilihan asas. */
    public const LIST = [
        'Selangor',
        'Kuala Lumpur',
        'Johor',
        'Kedah',
        'Kelantan',
        'Melaka',
        'Negeri Sembilan',
        'Pahang',
        'Perak',
        'Perlis',
        'Pulau Pinang',
        'Sabah',
        'Sarawak',
        'Terengganu',
    ];

    /** Wilayah Persekutuan tambahan yang turut boleh dipilih. */
    public const EXTRA = [
        'Putrajaya',
        'Labuan',
    ];

    /**
     * Semua pilihan (14 + 2 wilayah tambahan), tanpa duplikasi.
     *
     * @return array<int, string>
     */
    public static function all(): array
    {
        return array_values(array_unique([...self::LIST, ...self::EXTRA]));
    }
}
