<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class NoEmojiInTitle implements ValidationRule
{
    private const EMOJI_PATTERN = '/[\p{Extended_Pictographic}\x{1F1E6}-\x{1F1FF}\x{20E3}\x{FE0F}]/u';

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (is_string($value) && preg_match(self::EMOJI_PATTERN, $value) === 1) {
            $fail('Tajuk tidak boleh mengandungi emoji. Guna teks biasa sahaja.');
        }
    }
}
