<?php

namespace App\Support;

class NameMatcher
{
    /**
     * Normalise a person's name for duplicate comparison: lower-case, strip
     * punctuation, and drop common honorifics / relational words.
     */
    public static function normalize(?string $name): string
    {
        $name = mb_strtolower(trim((string) $name));
        $name = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $name) ?? '';
        $tokens = preg_split('/\s+/', trim($name)) ?: [];

        $stopWords = [
            'bin', 'binti', 'bt', 'b', 'hj', 'hjh', 'haji', 'hajjah',
            'dato', 'datuk', 'datin', 'dr', 'prof', 'ir', 'h',
        ];

        $tokens = array_values(array_filter(
            $tokens,
            fn (string $token) => strlen($token) > 1 && ! in_array($token, $stopWords, true),
        ));

        return implode(' ', $tokens);
    }

    /**
     * Compare names without merging people who merely share a surname (e.g.
     * couples sharing a phone or email).
     */
    public static function similar(?string $a, ?string $b): bool
    {
        $a = self::normalize($a);
        $b = self::normalize($b);

        if ($a === '' || $b === '') {
            return false;
        }

        if ($a === $b) {
            return true;
        }

        similar_text($a, $b, $percent);

        if ($percent >= 85.0) {
            return true;
        }

        $aTokens = explode(' ', $a);
        $bTokens = explode(' ', $b);
        $shared = array_intersect($aTokens, $bTokens);

        if (count($shared) >= 2) {
            return count($shared) / max(1, min(count($aTokens), count($bTokens))) >= 0.5;
        }

        if (count($shared) === 1 && min(count($aTokens), count($bTokens)) <= 2) {
            $aRemaining = array_values(array_diff($aTokens, $shared));
            $bRemaining = array_values(array_diff($bTokens, $shared));

            foreach ($aRemaining as $aToken) {
                foreach ($bRemaining as $bToken) {
                    if (strlen($aToken) >= 5 && strlen($bToken) >= 5 && levenshtein($aToken, $bToken) <= 1) {
                        return true;
                    }
                }
            }
        }

        return false;
    }
}
