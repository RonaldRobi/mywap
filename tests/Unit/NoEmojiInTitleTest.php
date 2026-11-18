<?php

namespace Tests\Unit;

use App\Rules\NoEmojiInTitle;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class NoEmojiInTitleTest extends TestCase
{
    public function test_rejects_emoji_in_title(): void
    {
        foreach (['Program 🚀', 'Infaq ✅ Ramadan', 'Borang 1️⃣', 'Kenyataan 🇲🇾'] as $title) {
            $validator = Validator::make(['title' => $title], ['title' => [new NoEmojiInTitle]]);
            $this->assertTrue($validator->fails(), "Gagal menolak tajuk: {$title}");
        }
    }

    public function test_allows_plain_text_and_punctuation(): void
    {
        foreach (['Program Ihya Ramadan', 'Infaq Am: Derma Bebas', 'Borang "Pendaftaran" (2026)', 'Kenyataan Media: 5 & 6 - OK?'] as $title) {
            $validator = Validator::make(['title' => $title], ['title' => [new NoEmojiInTitle]]);
            $this->assertFalse($validator->fails(), "Tersalah tolak tajuk: {$title}");
        }
    }
}
