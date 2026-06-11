<?php

namespace App\Http\Middleware;

use App\Models\AppSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * We eagerly load the `organization` relation so that every page component
     * receives the current NGO's slug and color_theme for dynamic UI theming —
     * without each controller having to load it manually.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();
        $isSuperadmin = $user?->hasRole('Superadmin') ?? false;
        $appSetting = Schema::hasTable('app_settings')
            ? AppSetting::query()->first()
            : null;

        return [
            ...parent::share($request),
            'flash' => [
                'success' => $request->session()->get('success'),
                'error'   => $request->session()->get('error'),
                'info'    => $request->session()->get('info'),
            ],
            'auth' => [
                'user' => $user ? [
                    'id'    => $user->id,
                    'name'  => $user->name,
                    'email' => $user->email,
                    'ic_number' => $user->ic_number,
                    'phone' => $user->phone,
                    'dob'   => $user->dob?->format('Y-m-d'),
                    'education_level' => $user->education_level,
                    'current_profession' => $user->current_profession,
                    'industry' => $user->industry,
                    'branch_name' => $user->branch_name,
                    'locality' => $user->locality,
                    'profile_photo_path' => $user->profile_photo_path,
                    'expertise' => $user->expertise,
                    'linkedin_url' => $user->linkedin_url,
                    'is_public_in_directory' => (bool) $user->is_public_in_directory,
                    'roles' => $user->getRoleNames(),
                    'organization' => $isSuperadmin
                        ? [
                            'id' => null,
                            'name' => 'Management',
                            'slug' => 'management',
                            'color_theme' => '#334155',
                        ]
                        : ($user->organization ? [
                            'id'          => $user->organization->id,
                            'name'        => $user->organization->name,
                            'slug'        => $user->organization->slug,
                            'color_theme' => $user->organization->color_theme,
                            'logo_path'   => $this->normalizeStorageUrl($user->organization->logo_path),
                        ] : null),
                ] : null,
            ],
            'brand' => [
                'system_logo_path' => $this->normalizeStorageUrl($appSetting?->system_logo_path),
                'splash_image_path' => $this->normalizeStorageUrl($appSetting?->splash_image_path),
                'splash_background_color' => $appSetting?->splash_background_color ?? '#0f172a',
                'splash_title' => $appSetting?->splash_title ?? 'myWAP',
                'splash_duration_ms' => (int) ($appSetting?->splash_duration_ms ?? 1800),
                'splash_enabled' => (bool) ($appSetting?->splash_enabled ?? true),
            ],
            'notifications' => $user ? [
                'unread_count' => $user->unreadNotifications()->count(),
                'recent' => $user->notifications()
                    ->latest()
                    ->take(8)
                    ->get()
                    ->map(fn ($notification) => [
                        'id' => $notification->id,
                        'title' => $notification->data['title'] ?? 'Notifikasi',
                        'content' => $notification->data['content'] ?? '',
                        'is_read' => ! is_null($notification->read_at),
                        'created_at' => $notification->created_at?->diffForHumans(),
                    ])->values(),
            ] : [
                'unread_count' => 0,
                'recent' => [],
            ],
        ];
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
