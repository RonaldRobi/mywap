<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Manual;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Senarai manual untuk aplikasi mobile.
 *
 * Menapis mengikut peranan pengguna: admin organisasi menerima manual
 * `admin`, manakala ahli biasa menerima manual `user`. Manual dengan
 * audience `all` dipaparkan kepada kedua-duanya.
 */
class ManualController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $isAdmin = $user
            && method_exists($user, 'hasRole')
            && $user->hasRole(['Superadmin', 'Admin', 'org-admin', 'Admin Cawangan']);

        $audience = $isAdmin ? 'admin' : 'user';

        $manuals = Manual::query()
            ->where('is_active', true)
            ->whereIn('audience', [$audience, 'all'])
            ->orderBy('sort_order')
            ->orderBy('title')
            ->get()
            ->map(fn (Manual $manual) => [
                'id' => $manual->id,
                'title' => $manual->title,
                'description' => $manual->description,
                'audience' => $manual->audience,
                'file_url' => $manual->file_path ? url($manual->file_path) : null,
                'updated_at' => $manual->updated_at?->toIso8601String(),
            ])
            ->values()
            ->all();

        return ApiResponse::success([
            'audience' => $audience,
            'manuals' => $manuals,
        ]);
    }
}
