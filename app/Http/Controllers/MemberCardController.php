<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;

class MemberCardController extends Controller
{
    public function show(Request $request): Response
    {
        $user = $request->user()->load('organization');
        $setting = Schema::hasTable('app_settings')
            ? AppSetting::query()->first()
            : null;

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
                'organization' => [
                    'name' => $user->organization?->name,
                    'slug' => $user->organization?->slug,
                    'logo_path' => $this->normalizeStorageUrl($user->organization?->logo_path),
                ],
                'photo_url' => $user->profile_photo_path,
                'member_since' => optional($user->created_at)->format('M Y'),
                'system_logo_path' => $this->normalizeStorageUrl($setting?->system_logo_path),
            ],
        ]);
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
