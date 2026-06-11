<?php

use App\Models\Infaq;
use Illuminate\Support\Facades\Storage;

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$seeds = [
    [
        'title' => 'Infaq Masjid Al-Iman',
        'description' => 'Bantu kami membina kemudahan solat yang lebih selesa untuk komuniti.',
        'type' => 'progress',
        'target_amount' => 50000,
        'collected_amount' => 23750,
        'display_order' => 1,
    ],
    [
        'title' => 'Infaq Anak Yatim Ramadan',
        'description' => 'Sumbangan untuk anak-anak yatim sempena bulan Ramadan yang mulia.',
        'type' => 'one_off',
        'target_amount' => null,
        'collected_amount' => 8100,
        'display_order' => 2,
    ],
    [
        'title' => 'Dana Pendidikan Islam',
        'description' => 'Tajaan kelas Quran & fardhu ain untuk pelajar kurang berkemampuan.',
        'type' => 'progress',
        'target_amount' => 15000,
        'collected_amount' => 9600,
        'display_order' => 3,
    ],
    [
        'title' => 'Infaq Buku & Pustaka',
        'description' => 'Sumbangkan untuk pengembangan koleksi buku perpustakaan komuniti.',
        'type' => 'progress',
        'target_amount' => 8000,
        'collected_amount' => 4200,
        'display_order' => 4,
    ],
    [
        'title' => 'Infaq Am — Derma Bebas',
        'description' => 'Sumbangan am untuk kegunaan operasi pertubuhan.',
        'type' => 'one_off',
        'target_amount' => null,
        'collected_amount' => 3300,
        'display_order' => 5,
    ],
];

$palettes = [
    ['from' => '#059669', 'to' => '#065f46', 'text' => '#d1fae5'],
    ['from' => '#6366f1', 'to' => '#3730a3', 'text' => '#e0e7ff'],
    ['from' => '#f59e0b', 'to' => '#b45309', 'text' => '#fef3c7'],
    ['from' => '#0ea5e9', 'to' => '#0369a1', 'text' => '#e0f2fe'],
    ['from' => '#ec4899', 'to' => '#9d174d', 'text' => '#fce7f3'],
];

foreach ($seeds as $i => $seed) {
    $palette = $palettes[$i % count($palettes)];
    $shortTitle = mb_substr($seed['title'], 0, 30);

    $svg = <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="800" height="600" viewBox="0 0 800 600">
  <defs>
    <linearGradient id="bg" x1="0%" y1="0%" x2="100%" y2="100%">
      <stop offset="0%" style="stop-color:{$palette['from']};stop-opacity:1"/>
      <stop offset="100%" style="stop-color:{$palette['to']};stop-opacity:1"/>
    </linearGradient>
  </defs>
  <rect width="800" height="600" fill="url(#bg)"/>
  <circle cx="700" cy="80" r="180" fill="white" fill-opacity="0.06"/>
  <circle cx="120" cy="520" r="140" fill="white" fill-opacity="0.06"/>
  <text x="60" y="200" font-family="Arial, sans-serif" font-size="26" font-weight="bold" fill="{$palette['text']}" opacity="0.8">INFAQ</text>
  <text x="60" y="260" font-family="Arial, sans-serif" font-size="38" font-weight="900" fill="white">{$shortTitle}</text>
  <text x="60" y="320" font-family="Arial, sans-serif" font-size="20" fill="white" opacity="0.75">Derma &amp; Sumbangan</text>
  <rect x="60" y="380" width="120" height="4" rx="2" fill="white" fill-opacity="0.5"/>
</svg>
SVG;

    $filename = 'infaq/demo_infaq_' . ($i + 1) . '.svg';
    Storage::disk('public')->put($filename, $svg);
    $imagePath = Storage::disk('public')->url($filename);

    Infaq::query()->updateOrCreate(
        ['title' => $seed['title']],
        array_merge($seed, [
            'organization_id' => null,
            'image_path' => $imagePath,
            'is_active' => true,
        ])
    );

    echo "✔ Seeded: {$seed['title']}\n";
}

echo "\nDone. Seeded " . count($seeds) . " infaq items.\n";
