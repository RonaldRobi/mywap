<?php

namespace App\Support;

use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Encoder\Encoder;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

/**
 * QrPng
 *
 * Mengeluarkan QR code sebagai binary PNG yang sah untuk download.
 * Satu sumber untuk semua modul (event, infaq, borang, undian) supaya tingkah
 * laku konsisten dan mudah diselenggara.
 *
 *   - Backend Imagick dipakai bila extension tersedia (output paling tajam).
 *   - Jika tidak, fallback ke renderer GD tulen supaya PNG tetap boleh dijana
 *     walaupun tanpa Imagick.
 *   - BaconQrCode mengeluarkan E_DEPRECATED pada PHP 8.4 (contoh:
 *     Encoder::chooseMode, ReedSolomonCodec::decode). Jika display_errors aktif,
 *     teks warning itu tercetak sebelum binary PNG dan merosakkan fail muat
 *     turun — maka seluruh proses render dibalut dengan penahanan deprecation.
 */
class QrPng
{
    public static function render(string $text, int $size = 1024, int $margin = 2, string $errorCorrection = 'H'): string
    {
        set_error_handler(static fn (int $severity): bool => ($severity & E_DEPRECATED) !== 0);

        try {
            if (extension_loaded('imagick')) {
                return (string) QrCode::format('png')
                    ->size($size)
                    ->margin($margin)
                    ->errorCorrection($errorCorrection)
                    ->generate($text);
            }

            return self::renderWithGd($text, $size, $margin, $errorCorrection);
        } finally {
            restore_error_handler();
        }
    }

    private static function renderWithGd(string $text, int $size, int $margin, string $errorCorrection): string
    {
        $ecLevel = match (strtoupper($errorCorrection)) {
            'L' => ErrorCorrectionLevel::L(),
            'M' => ErrorCorrectionLevel::M(),
            'Q' => ErrorCorrectionLevel::Q(),
            default => ErrorCorrectionLevel::H(),
        };

        $matrix = Encoder::encode($text, $ecLevel)->getMatrix();
        $modules = $matrix->getWidth();
        $total = $modules + ($margin * 2);

        $scale = (int) floor($size / $total);
        if ($scale < 1) {
            $scale = 1;
        }
        $pixelSize = $total * $scale;

        $image = imagecreatetruecolor($pixelSize, $pixelSize);
        $white = imagecolorallocate($image, 255, 255, 255);
        $black = imagecolorallocate($image, 0, 0, 0);
        imagefilledrectangle($image, 0, 0, $pixelSize, $pixelSize, $white);

        for ($y = 0; $y < $modules; $y++) {
            for ($x = 0; $x < $modules; $x++) {
                if ($matrix->get($x, $y)) {
                    imagefilledrectangle(
                        $image,
                        ($x + $margin) * $scale,
                        ($y + $margin) * $scale,
                        (($x + $margin) * $scale) + $scale - 1,
                        (($y + $margin) * $scale) + $scale - 1,
                        $black
                    );
                }
            }
        }

        ob_start();
        imagepng($image);
        $png = (string) ob_get_clean();
        imagedestroy($image);

        return $png;
    }
}
