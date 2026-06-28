<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class MemberCardController extends Controller
{
    public function show(Request $request): Response
    {
        $user = $request->user()->load('organization');
        $setting = Schema::hasTable('app_settings')
            ? AppSetting::query()->first()
            : null;

        $privateUrl = route('member.card');
        $publicUrl = $user->member_no ? route('public.card', ['memberNo' => $user->member_no]) : null;

        $privateQr = $this->generateQrSvg($privateUrl);
        $publicQr = $publicUrl ? $this->generateQrSvg($publicUrl) : null;

        return Inertia::render('Member/Card', [
            'card' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'branch_name' => $user->branch_name,
                'locality' => $user->locality,
                'profession' => $user->current_profession,
                'industry' => $user->industry,
                'member_no' => $user->member_no,
                'organization' => [
                    'name' => $user->organization?->name,
                    'slug' => $user->organization?->slug,
                    'logo_path' => $this->normalizeStorageUrl($user->organization?->logo_path),
                ],
                'photo_url' => $user->profile_photo_path,
                'member_since' => optional($user->created_at)->format('M Y'),
                'system_logo_path' => $this->normalizeStorageUrl($setting?->system_logo_path),
            ],
            'qrPrivate' => $privateQr,
            'qrPublic' => $publicQr,
        ]);
    }

    private function generateQrSvg(string $url): string
    {
        $svg = QrCode::format('svg')->size(200)->margin(1)->generate($url);
        $svg = preg_replace('/^<\?xml.*?\?>\s*/', '', $svg);
        $svg = preg_replace('/\s(width|height)="\d+"/', '', $svg);
        return $svg;
    }

    private function normalizeStorageUrl(?string $url): ?string
    {
        if (! $url) {
            return null;
        }

        $parsedPath = parse_url($url, PHP_URL_PATH);

        if (is_string($parsedPath) && str_starts_with($parsedPath, '/storage/')) {
            return $parsedPath;
        }

        return $url;
    }
}
