<?php

use App\Models\DashboardBanner;
use App\Models\Organization;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$organizations = Organization::query()->orderBy('id')->get();
if ($organizations->isEmpty()) {
    echo "No organizations found.\n";
    exit(1);
}

$globalBanners = [
    ['title' => 'Minggu Ukhuwah Nasional', 'display_order' => 1],
    ['title' => 'Kempen Infaq Ramadan', 'display_order' => 2],
    ['title' => 'Jelajah Kepimpinan Belia', 'display_order' => 3],
];

$orgBannerTemplates = [
    ['title' => 'Forum Komuniti {ORG}', 'display_order' => 10],
    ['title' => 'Program Sukarelawan {ORG}', 'display_order' => 11],
];

$palette = [
    ['#065f46', '#10b981'],
    ['#0f766e', '#14b8a6'],
    ['#334155', '#0ea5e9'],
    ['#7c3aed', '#a78bfa'],
    ['#be123c', '#fb7185'],
    ['#92400e', '#f59e0b'],
];

$makeBannerSvg = function (string $title, string $subtitle, string $startColor, string $endColor): string {
    $safeTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
    $safeSubtitle = htmlspecialchars($subtitle, ENT_QUOTES, 'UTF-8');

    return "<svg xmlns='http://www.w3.org/2000/svg' width='1080' height='1350'>"
        . "<defs><linearGradient id='g' x1='0' y1='0' x2='1' y2='1'>"
        . "<stop offset='0%' stop-color='{$startColor}'/><stop offset='100%' stop-color='{$endColor}'/>"
        . "</linearGradient></defs>"
        . "<rect width='1080' height='1350' fill='url(#g)'/>"
        . "<circle cx='940' cy='200' r='210' fill='rgba(255,255,255,0.12)'/>"
        . "<circle cx='180' cy='1120' r='170' fill='rgba(255,255,255,0.10)'/>"
        . "<rect x='70' y='80' width='940' height='1190' rx='36' fill='rgba(255,255,255,0.10)' stroke='rgba(255,255,255,0.26)'/>"
        . "<text x='120' y='220' fill='white' font-family='Arial, sans-serif' font-size='38' font-weight='700'>{$safeSubtitle}</text>"
        . "<foreignObject x='120' y='290' width='840' height='640'>"
        . "<div xmlns='http://www.w3.org/1999/xhtml' style='font-family:Arial,sans-serif;color:white;font-size:82px;font-weight:900;line-height:1.08;'>{$safeTitle}</div>"
        . "</foreignObject>"
        . "<text x='120' y='1220' fill='white' font-family='Arial, sans-serif' font-size='30'>MyMarhalah · Berita Bergambar</text>"
        . "</svg>";
};

$index = 0;

foreach ($globalBanners as $banner) {
    [$startColor, $endColor] = $palette[$index % count($palette)];
    $slug = Str::slug($banner['title']);
    $storagePath = "dashboard-banners/dummy/global-{$slug}.svg";

    $svg = $makeBannerSvg($banner['title'], 'Global Announcement', $startColor, $endColor);
    Storage::disk('public')->put($storagePath, $svg);

    DashboardBanner::query()->updateOrCreate(
        [
            'organization_id' => null,
            'title' => $banner['title'],
        ],
        [
            'image_path' => Storage::disk('public')->url($storagePath),
            'is_active' => true,
            'display_order' => $banner['display_order'],
        ]
    );

    $index++;
}

echo "Global banners seeded: " . count($globalBanners) . "\n";

foreach ($organizations as $org) {
    foreach ($orgBannerTemplates as $template) {
        [$startColor, $endColor] = $palette[$index % count($palette)];

        $title = str_replace('{ORG}', $org->name, $template['title']);
        $slug = Str::slug($title);
        $storagePath = "dashboard-banners/dummy/org-{$org->id}-{$slug}.svg";

        $svg = $makeBannerSvg($title, $org->name, $startColor, $endColor);
        Storage::disk('public')->put($storagePath, $svg);

        DashboardBanner::query()->updateOrCreate(
            [
                'organization_id' => $org->id,
                'title' => $title,
            ],
            [
                'image_path' => Storage::disk('public')->url($storagePath),
                'is_active' => true,
                'display_order' => $template['display_order'],
            ]
        );

        $index++;
    }

    echo "Organization banners seeded for {$org->name}: " . count($orgBannerTemplates) . "\n";
}

echo "Done.\n";
