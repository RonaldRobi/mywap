<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;

class PublicCardController extends Controller
{
    public function show(string $memberNo): Response
    {
        $user = User::where('member_no', $memberNo)->with('organization')->firstOrFail();
        $setting = Schema::hasTable('app_settings')
            ? AppSetting::query()->first()
            : null;

        return Inertia::render('Public/Card', [
            'card' => [
                'name' => $user->name,
                'member_no' => $user->member_no,
                'member_since' => optional($user->created_at)->format('M Y'),
                'photo_url' => $user->profile_photo_path,
                'organization' => [
                    'name' => $user->organization?->name,
                    'slug' => $user->organization?->slug,
                    'logo_path' => $user->organization?->logo_path
                        ? $this->normalizeUrl($user->organization->logo_path)
                        : null,
                ],
                'system_logo_path' => $setting?->system_logo_path
                    ? $this->normalizeUrl($setting->system_logo_path)
                    : null,
            ],
        ]);
    }

    private function normalizeUrl(?string $url): ?string
    {
        if (! $url) return null;
        $parsed = parse_url($url, PHP_URL_PATH);
        if (is_string($parsed) && str_starts_with($parsed, '/storage/')) {
            return $parsed;
        }
        return $url;
    }
}
