<?php

namespace App\Services;

use App\Models\AppSetting;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class DirectoryService
{
    /**
     * Directory payload — users (paginated per 16), industries & active filters.
     * Dikongsi oleh web (Inertia) dan API (JSON) supaya shape kekal sama.
     */
    public function directory(Request $request): array
    {
        $search = trim((string) $request->query('search', ''));
        $industry = trim((string) $request->query('industry', ''));

        $query = User::query()
            ->with('organization')
            ->where('is_public_in_directory', true)
            ->whereNotNull('name');

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('industry', 'like', "%{$search}%")
                    ->orWhere('expertise', 'like', "%{$search}%");
            });
        }

        if ($industry !== '') {
            $query->where('industry', $industry);
        }

        $users = $query->orderBy('name')
            ->paginate(16)
            ->withQueryString()
            ->through(fn (User $user) => $this->serializeDirectoryUser($user));

        $industries = User::query()
            ->where('is_public_in_directory', true)
            ->whereNotNull('industry')
            ->select('industry')
            ->distinct()
            ->orderBy('industry')
            ->pluck('industry')
            ->values();

        return [
            'users' => $users,
            'industries' => $industries,
            'filters' => [
                'search' => $search,
                'industry' => $industry,
            ],
        ];
    }

    /**
     * Serialize satu pengguna untuk senarai directory.
     */
    public function serializeDirectoryUser(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'industry' => $user->industry,
            'expertise' => $user->expertise,
            'linkedin_url' => $user->linkedin_url,
            'organization' => [
                'name' => $user->organization?->name,
                'slug' => $user->organization?->slug,
            ],
        ];
    }

    /**
     * Payload kad awam pengguna berdasarkan member_no. Sepenuhnya public.
     */
    public function publicCard(string $memberNo): array
    {
        $user = User::where('member_no', $memberNo)->with('organization')->firstOrFail();
        $setting = Schema::hasTable('app_settings')
            ? AppSetting::query()->first()
            : null;

        return [
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
        ];
    }

    private function normalizeUrl(?string $url): ?string
    {
        if (! $url) {
            return null;
        }
        $parsed = parse_url($url, PHP_URL_PATH);
        if (is_string($parsed) && str_starts_with($parsed, '/storage/')) {
            return $parsed;
        }

        return $url;
    }
}
