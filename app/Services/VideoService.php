<?php

namespace App\Services;

use App\Models\User;
use App\Models\Video;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

/**
 * VideoService
 *
 * Logik tunggal untuk domain Video — dikongsi oleh WebController (Inertia)
 * dan ApiController (JSON) supaya web & Flutter tidak drift.
 * Rujuk docs/FLUTTER_PLAN.md §8.
 */
class VideoService
{
    public function serialize(Video $video): array
    {
        return [
            'id' => $video->id,
            'title' => $video->title,
            'youtube_id' => $video->youtube_id,
            'thumbnail_url' => $video->thumbnail_url,
            'embed_url' => $video->embed_url,
        ];
    }

    /**
     * Senarai video (nilai org null + org sendiri), berpagina.
     */
    public function list(Request $request, User $user): LengthAwarePaginator
    {
        return Video::query()
            ->where(function ($query) use ($user) {
                $query->whereNull('organization_id')
                    ->orWhere('organization_id', $user->current_organization_id);
            })
            ->latest()
            ->paginate(self::perPage($request))
            ->withQueryString()
            ->through(fn (Video $video) => $this->serialize($video));
    }

    /**
     * Pastikan per_page hanya nilai yang dibenarkan (12/25/50/100 default 12).
     */
    public static function perPage(Request $request, int $default = 12): int
    {
        $perPage = (int) $request->input('per_page', $default);

        return in_array($perPage, [12, 25, 50, 100]) ? $perPage : $default;
    }
}
