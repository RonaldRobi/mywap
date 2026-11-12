<?php

namespace App\Support;

use App\Models\AppSetting;
use App\Models\Organization;

/**
 * Poster OG pendaftaran ahli (1200x630) untuk pratonton pautan dan muat turun.
 * Dirender dengan GD supaya tidak bergantung pada Imagick di pelayan.
 */
class RegistrationPoster
{
    private const WIDTH = 1200;

    private const HEIGHT = 630;

    public static function render(?Organization $organization, string $registerUrl): string
    {
        $image = imagecreatetruecolor(self::WIDTH, self::HEIGHT);

        $cream = imagecolorallocate($image, 247, 245, 239);
        $white = imagecolorallocate($image, 255, 255, 255);
        $ink = imagecolorallocate($image, 7, 21, 37);
        $muted = imagecolorallocate($image, 74, 90, 80);
        $line = imagecolorallocate($image, 213, 227, 216);
        $green = imagecolorallocate($image, 18, 61, 42);

        imagefilledrectangle($image, 0, 0, self::WIDTH, self::HEIGHT, $cream);

        $accent = self::hexColor($image, $organization?->color_theme ?? '#2F6B32');

        self::roundedRect($image, 48, 40, self::WIDTH - 96, self::HEIGHT - 80, 28, $white);
        imagefilledrectangle($image, 76, 40, self::WIDTH - 76, 48, $accent);
        imagerectangle($image, 48, 40, self::WIDTH - 48, self::HEIGHT - 40, $line);

        self::drawLogos($image, $organization, 92, 84);

        $organizationName = $organization?->name ?? 'myWAP';
        $titleSize = self::fitFontSize($organizationName, 62, 720, 580);
        $bodyFont = self::fontPath('DejaVuSans.ttf');
        $boldFont = self::fontPath('DejaVuSans-Bold.ttf');

        imagettftext($image, 26, 0, 92, 236, $muted, $bodyFont, 'Pendaftaran ahli dibuka');
        imagettftext($image, $titleSize, 0, 92, 318, $ink, $boldFont, $organizationName);
        imagettftext($image, 26, 0, 92, 380, $green, $boldFont, 'Percuma untuk semua organisasi');
        imagettftext($image, 20, 0, 92, 422, $muted, $bodyFont, 'Kad ahli, program, usrah dan komuniti dalam satu aplikasi.');

        self::roundedRect($image, 92, 472, 330, 66, 33, $green);
        imagettftext($image, 20, 0, 128, 512, $white, $boldFont, 'Daftar di myWAP');

        $host = parse_url($registerUrl, PHP_URL_HOST) ?: 'mywap.my';
        imagettftext($image, 18, 0, 92, 578, $muted, $bodyFont, $host);

        self::drawQrPanel($image, $registerUrl, 812, 118, 300, $ink, $muted, $line, $boldFont, $bodyFont);

        ob_start();
        imagepng($image);
        $png = (string) ob_get_clean();
        imagedestroy($image);

        return $png;
    }

    private static function drawLogos($image, ?Organization $organization, int $x, int $y): void
    {
        $cursor = $x;

        $orgLogo = self::loadLogo($organization?->logo_path);
        if ($orgLogo) {
            $scaled = self::scaleImage($orgLogo, 84, 84);
            imagecopy($image, $scaled, $cursor, $y, 0, 0, imagesx($scaled), imagesy($scaled));
            imagedestroy($scaled);
            imagedestroy($orgLogo);
            $cursor += 104;
        } elseif ($organization) {
            $initial = strtoupper(mb_substr($organization->name, 0, 1));
            $size = 70;
            self::roundedRect($image, $cursor, $y + 4, $size, $size, 20, self::hexColor($image, $organization->color_theme ?? '#2F6B32'));
            imagettftext($image, 30, 0, $cursor + 22, $y + 55, imagecolorallocate($image, 255, 255, 255), self::fontPath('DejaVuSans-Bold.ttf'), $initial);
            $cursor += 92;
        }

        $settings = AppSetting::singleton();
        $appLogo = self::loadLogo($settings->system_logo_path ?: '/images/logomywaphorizontal.png');
        if ($appLogo) {
            $scaled = self::scaleImage($appLogo, 190, 64);
            imagecopy($image, $scaled, $cursor, $y + 10, 0, 0, imagesx($scaled), imagesy($scaled));
            imagedestroy($scaled);
            imagedestroy($appLogo);
        } else {
            imagettftext($image, 26, 0, $cursor, $y + 54, imagecolorallocate($image, 7, 21, 37), self::fontPath('DejaVuSans-Bold.ttf'), 'myWAP');
        }
    }

    private static function drawQrPanel($image, string $url, int $x, int $y, int $size, int $ink, int $muted, int $line, string $boldFont, string $bodyFont): void
    {
        $panelWidth = $size + 44;
        $panelHeight = $size + 92;

        $panel = imagecolorallocate($image, 248, 250, 249);
        self::roundedRect($image, $x, $y, $panelWidth, $panelHeight, 24, $panel);
        imagerectangle($image, $x, $y, $x + $panelWidth, $y + $panelHeight, $line);

        $qrPng = QrPng::render($url, 600, 2);
        $qr = imagecreatefromstring($qrPng);

        if ($qr) {
            $scaled = imagecreatetruecolor($size, $size);
            imagecopyresampled($scaled, $qr, 0, 0, 0, 0, $size, $size, imagesx($qr), imagesy($qr));
            imagecopy($image, $scaled, $x + 22, $y + 22, 0, 0, $size, $size);
            imagedestroy($scaled);
            imagedestroy($qr);
        }

        $label = 'Imbas untuk daftar';
        $box = imagettfbbox(17, 0, $boldFont, $label);
        $textWidth = abs($box[2] - $box[0]);
        imagettftext($image, 17, 0, $x + (int) (($panelWidth - $textWidth) / 2), $y + $size + 62, $muted, $boldFont, $label);
    }

    private static function loadLogo(?string $path)
    {
        if (! $path) {
            return null;
        }

        $data = null;

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            $data = @file_get_contents($path);
        } else {
            $relative = ltrim($path, '/');
            $candidates = [
                public_path($relative),
                public_path('storage/'.ltrim(str_replace('storage/', '', $relative), '/')),
                storage_path('app/public/'.ltrim(str_replace('storage/', '', $relative), '/')),
            ];

            foreach ($candidates as $candidate) {
                if (is_file($candidate)) {
                    $data = @file_get_contents($candidate);
                    break;
                }
            }
        }

        if (! $data) {
            return null;
        }

        $image = @imagecreatefromstring($data);

        return $image ?: null;
    }

    private static function scaleImage($source, int $maxWidth, int $maxHeight)
    {
        $width = imagesx($source);
        $height = imagesy($source);
        $ratio = min($maxWidth / $width, $maxHeight / $height, 1);

        $targetWidth = max(1, (int) round($width * $ratio));
        $targetHeight = max(1, (int) round($height * $ratio));

        $target = imagecreatetruecolor($targetWidth, $targetHeight);
        imagealphablending($target, false);
        imagesavealpha($target, true);
        $transparent = imagecolorallocatealpha($target, 0, 0, 0, 127);
        imagefilledrectangle($target, 0, 0, $targetWidth, $targetHeight, $transparent);
        imagecopyresampled($target, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);

        return $target;
    }

    private static function fitFontSize(string $text, int $maxSize, int $maxWidth, int $minSize): int
    {
        $font = self::fontPath('DejaVuSans-Bold.ttf');
        $size = $maxSize;

        while ($size > $minSize) {
            $box = imagettfbbox($size, 0, $font, $text);
            if (abs($box[2] - $box[0]) <= $maxWidth) {
                break;
            }
            $size -= 2;
        }

        return $size;
    }

    private static function fontPath(string $file): string
    {
        $candidates = [
            base_path('vendor/dompdf/dompdf/lib/fonts/'.$file),
            '/System/Library/Fonts/Supplemental/Arial.ttf',
        ];

        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return base_path('vendor/dompdf/dompdf/lib/fonts/DejaVuSans.ttf');
    }

    private static function hexColor($image, string $hex): int
    {
        $hex = ltrim($hex, '#');

        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }

        if (strlen($hex) !== 6 || ! ctype_xdigit($hex)) {
            $hex = '2F6B32';
        }

        return imagecolorallocate(
            $image,
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2))
        );
    }

    private static function roundedRect($image, int $x, int $y, int $width, int $height, int $radius, int $color, bool $fill = true): void
    {
        if ($fill) {
            imagefilledrectangle($image, $x + $radius, $y, $x + $width - $radius, $y + $height, $color);
            imagefilledrectangle($image, $x, $y + $radius, $x + $width, $y + $height - $radius, $color);
            imagefilledellipse($image, $x + $radius, $y + $radius, $radius * 2, $radius * 2, $color);
            imagefilledellipse($image, $x + $width - $radius, $y + $radius, $radius * 2, $radius * 2, $color);
            imagefilledellipse($image, $x + $radius, $y + $height - $radius, $radius * 2, $radius * 2, $color);
            imagefilledellipse($image, $x + $width - $radius, $y + $height - $radius, $radius * 2, $radius * 2, $color);
        }
    }
}
