<?php

namespace App\Services;

use App\Models\Organization;
use App\Models\User;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class RegistrationShareService
{
    public function forUser(User $user): array
    {
        $organization = $user->hasRole('Superadmin') ? null : $user->organization;
        $referralCode = $user->member_no ?: null;

        $context = $this->context($organization, $referralCode);

        $query = array_filter([
            'org' => $organization?->slug,
            'ref' => $referralCode,
        ]);

        return $context + [
            'qr_svg' => $this->qrSvg($context['register_url']),
            'qr_png_url' => route('admin.jemput-ahli.qr', $query),
            'poster_url' => route('share.register.image', $query),
            'poster_download_url' => route('admin.jemput-ahli.poster', $query),
        ];
    }

    public function context(?Organization $organization, ?string $referralCode = null): array
    {
        $organizationName = $organization?->name ?? 'myWAP';

        $registerParams = $referralCode ? ['ref' => $referralCode] : [];
        $registerUrl = route('register', $registerParams);

        $shareParams = array_filter([
            'org' => $organization?->slug,
            'ref' => $referralCode,
        ]);
        $shareUrl = route('share.register', $shareParams);

        $title = $organization
            ? "Jemput Ahli {$organizationName}"
            : 'Jemput Ahli myWAP';

        $description = $organization
            ? "Pendaftaran ahli {$organizationName} dibuka. Percuma untuk semua organisasi. Organisasi ahli ditentukan mengikut tahun lahir."
            : 'Pendaftaran ahli myWAP dibuka untuk PKPIM, ABIM dan WADAH. Percuma untuk semua organisasi.';

        $message = "Assalamualaikum. Kami menjemput anda mendaftar sebagai ahli melalui myWAP. "
            .'Pendaftaran percuma dan organisasi ditentukan mengikut tahun lahir. '
            ."Daftar di sini: {$shareUrl}";

        return [
            'organization' => $organization ? [
                'id' => $organization->id,
                'name' => $organization->name,
                'slug' => $organization->slug,
                'color_theme' => $organization->color_theme,
                'logo_url' => $this->logoUrl($organization->logo_path),
            ] : null,
            'title' => $title,
            'description' => $description,
            'message' => $message,
            'register_url' => $registerUrl,
            'share_url' => $shareUrl,
        ];
    }

    private function logoUrl(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        if (! str_starts_with($path, '/')) {
            $path = '/storage/'.ltrim($path, '/');
        }

        return url($path);
    }

    public function qrSvg(string $url): string
    {
        $svg = (string) QrCode::format('svg')
            ->size(320)
            ->errorCorrection('H')
            ->margin(2)
            ->generate($url);

        $svg = preg_replace('/^<\?xml.*?\?>\s*/', '', $svg) ?? $svg;
        $svg = preg_replace('/\s(width|height)="\d+"/', '', $svg) ?? $svg;

        return $svg;
    }
}
