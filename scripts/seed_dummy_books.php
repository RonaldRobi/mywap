<?php

use App\Models\LibraryItem;
use App\Models\Organization;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$organizations = Organization::query()->orderBy('id')->get();
if ($organizations->isEmpty()) {
    echo "No organization found.\n";
    exit(1);
}

$makePdf = function (string $text): string {
    $escapedText = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
    $stream = "BT /F1 18 Tf 40 150 Td ({$escapedText}) Tj ET";

    $objects = [];
    $objects[] = "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n";
    $objects[] = "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n";
    $objects[] = "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>\nendobj\n";
    $objects[] = "4 0 obj\n<< /Length " . strlen($stream) . " >>\nstream\n{$stream}\nendstream\nendobj\n";
    $objects[] = "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n";

    $pdf = "%PDF-1.4\n";
    $offsets = [];

    foreach ($objects as $obj) {
        $offsets[] = strlen($pdf);
        $pdf .= $obj;
    }

    $xrefStart = strlen($pdf);
    $pdf .= "xref\n0 " . (count($objects) + 1) . "\n";
    $pdf .= "0000000000 65535 f \n";

    foreach ($offsets as $offset) {
        $pdf .= sprintf("%010d 00000 n \n", $offset);
    }

    $pdf .= "trailer\n<< /Size " . (count($objects) + 1) . " /Root 1 0 R >>\n";
    $pdf .= "startxref\n{$xrefStart}\n%%EOF";

    return $pdf;
};

$books = [
    ['title' => 'Kunci Dakwah Generasi', 'category' => 'Tarbiah', 'description' => 'Dummy buku tarbiah untuk semakan UI.'],
    ['title' => 'Panduan Usrah Mingguan', 'category' => 'Modul', 'description' => 'Contoh modul usrah dalam format PDF.'],
    ['title' => 'Fiqh Masyarakat Madani', 'category' => 'Ilmiah', 'description' => 'Bahan bacaan komuniti.'],
    ['title' => 'Strategi Belia Berimpak', 'category' => 'Kepimpinan', 'description' => 'Rujukan kepimpinan belia.'],
    ['title' => 'Adab Aktivis Muslim', 'category' => 'Tazkiyah', 'description' => 'Ringkasan adab dan akhlak gerakan.'],
];

$colorPairs = [
    ['#7dd3fc', '#0284c7'],
    ['#86efac', '#16a34a'],
    ['#c4b5fd', '#7c3aed'],
    ['#fcd34d', '#f97316'],
    ['#a5b4fc', '#2563eb'],
];

foreach ($organizations as $org) {
    foreach ($books as $index => $book) {
        $slug = Str::slug($book['title']) . '-' . ($index + 1);
        $pdfStoragePath = "library/dummy/org-{$org->id}/{$slug}.pdf";
        $coverStoragePath = "library/covers/org-{$org->id}/{$slug}.svg";

        Storage::disk('public')->put($pdfStoragePath, $makePdf($book['title']));

        [$startColor, $endColor] = $colorPairs[$index % count($colorPairs)];
        $safeTitle = htmlspecialchars($book['title'], ENT_QUOTES, 'UTF-8');
        $safeCategory = htmlspecialchars($book['category'], ENT_QUOTES, 'UTF-8');

        $coverSvg = "<svg xmlns='http://www.w3.org/2000/svg' width='420' height='600'>"
            . "<defs><linearGradient id='g' x1='0' y1='0' x2='0' y2='1'>"
            . "<stop offset='0%' stop-color='{$startColor}'/>"
            . "<stop offset='100%' stop-color='{$endColor}'/>"
            . "</linearGradient></defs>"
            . "<rect width='420' height='600' fill='url(#g)'/>"
            . "<rect x='24' y='24' width='372' height='552' rx='16' fill='rgba(255,255,255,0.15)' stroke='rgba(255,255,255,0.35)'/>"
            . "<text x='36' y='80' fill='white' font-family='Arial, sans-serif' font-size='20' font-weight='700'>{$safeCategory}</text>"
            . "<foreignObject x='36' y='130' width='348' height='350'>"
            . "<div xmlns='http://www.w3.org/1999/xhtml' style='font-family:Arial,sans-serif;color:white;font-size:38px;font-weight:800;line-height:1.15;'>{$safeTitle}</div>"
            . "</foreignObject>"
            . "<text x='36' y='550' fill='white' font-family='Arial, sans-serif' font-size='15'>MyMarhalah Dummy Cover</text>"
            . "</svg>";

        Storage::disk('public')->put($coverStoragePath, $coverSvg);

        LibraryItem::query()->withoutGlobalScopes()->updateOrCreate(
            [
                'organization_id' => $org->id,
                'title' => $book['title'],
            ],
            [
                'description' => $book['description'],
                'category' => $book['category'],
                'file_path' => Storage::disk('public')->url($pdfStoragePath),
                'cover_image_path' => Storage::disk('public')->url($coverStoragePath),
            ]
        );
    }

    echo "Dummy books uploaded for organization: {$org->name}\n";
}
