<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;

/**
 * Carian ahli yang konsisten untuk web admin & API mobile.
 *
 * Ciri:
 *  - Pecahkan pertanyaan pada SEMUA jenis ruang putih / aksara halimunan
 *    (termasuk NBSP dari import Excel, zero-width space, BOM) supaya
 *    "Ahmad Firdaus" padan dengan "Ahmad\u{00A0}Firdaus" atau berbilang ruang.
 *  - Padanan tidak sensitif huruf besar/kecil merentas semua driver DB.
 *  - Padanan OR antara token + susunan relevans supaya nama penuh yang
 *    mengandungi perkataan tambahan (cth. "bin Abdullah") tetap muncul.
 */
class MemberSearch
{
    /** Kolum yang dicari. */
    public const COLUMNS = [
        'name',
        'email',
        'phone',
        'ic_number',
        'member_no',
        'original_member_no',
    ];

    /**
     * @return list<string>
     */
    public static function tokens(mixed $search): array
    {
        $search = is_string($search) ? trim($search) : '';
        if ($search === '') {
            return [];
        }

        // Semua ruang putih Unicode + aksara halimunan → ruang biasa.
        $normalized = preg_replace(
            '/[\s\x{00A0}\x{1680}\x{180E}\x{2000}-\x{200F}\x{2028}\x{2029}\x{202F}\x{205F}\x{3000}\x{FEFF}]+/u',
            ' ',
            $search,
        );

        if (! is_string($normalized)) {
            $normalized = $search;
        }

        $tokens = preg_split('/ +/u', trim($normalized), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_values(array_unique($tokens));
    }

    /**
     * Guna pada query User. Memadankan jika MANA-MANA token sepadan,
     * kemudian susun mengikut bilangan token yang padan pada `name`.
     *
     * @param  list<string>  $columns
     */
    public static function apply(Builder $query, mixed $search, array $columns = self::COLUMNS): void
    {
        $tokens = self::tokens($search);
        if ($tokens === []) {
            return;
        }

        $query->where(function (Builder $outer) use ($tokens, $columns) {
            foreach ($tokens as $token) {
                $like = self::like($token);
                $outer->orWhere(function (Builder $inner) use ($columns, $like) {
                    foreach ($columns as $column) {
                        $inner->orWhereRaw("LOWER({$column}) LIKE ?", [$like]);
                    }
                });
            }
        });

        self::orderByRelevance($query, $tokens);
    }

    /**
     * Susun supaya nama yang mengandungi lebih banyak token carian di atas.
     *
     * @param  list<string>  $tokens
     */
    public static function orderByRelevance(Builder $query, array $tokens): void
    {
        if ($tokens === []) {
            return;
        }

        $parts = [];
        $bindings = [];
        foreach ($tokens as $token) {
            $parts[] = '(CASE WHEN LOWER(name) LIKE ? THEN 1 ELSE 0 END)';
            $bindings[] = self::like($token);
        }

        $query->orderByRaw('('.implode(' + ', $parts).') DESC', $bindings);
    }

    private static function like(string $token): string
    {
        return '%'.addcslashes(mb_strtolower($token), '\\%_').'%';
    }
}
