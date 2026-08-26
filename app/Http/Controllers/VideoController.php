<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\Video;
use App\Services\VideoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class VideoController extends Controller
{
    public function __construct(private readonly VideoService $videos) {}

    public function manage(Request $request): Response
    {
        $user = $request->user();
        $isSuperadmin = $user->hasRole('Superadmin');

        $videos = Video::query()
            ->with('organization:id,name')
            ->when(! $isSuperadmin, function ($query) use ($user) {
                $query->where('organization_id', $user->current_organization_id);
            })
            ->latest()
            ->get()
            ->map(fn (Video $video) => [
                'id' => $video->id,
                'title' => $video->title,
                'youtube_url' => $video->youtube_url,
                'youtube_id' => $video->youtube_id,
                'thumbnail_url' => $video->thumbnail_url,
                'embed_url' => $video->embed_url,
                'is_live' => $video->is_live,
                'organization_id' => $video->organization_id,
                'organization_name' => $video->organization?->name ?? 'Global',
                'created_at' => $video->created_at->diffForHumans(),
            ]);

        return Inertia::render('VideoManage', [
            'organizations' => $isSuperadmin
                ? Organization::query()->orderBy('name')->get(['id', 'name'])
                : [],
            'videos' => $videos,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        $isSuperadmin = $user->hasRole('Superadmin');

        $data = $request->validate([
            'organization_id' => ['nullable', 'integer', 'exists:organizations,id'],
            'title' => ['required', 'string', 'max:255'],
            'youtube_url' => ['required', 'string', 'max:500'],
            'is_live' => ['boolean'],
        ]);

        $youtubeId = $this->extractYoutubeId($data['youtube_url']);

        if (! $youtubeId) {
            return back()->with('error', 'URL YouTube tidak sah.');
        }

        Video::create([
            'organization_id' => $isSuperadmin ? ($data['organization_id'] ?? null) : $user->current_organization_id,
            'title' => $data['title'],
            'youtube_url' => $data['youtube_url'],
            'youtube_id' => $youtubeId,
            'is_live' => (bool) ($data['is_live'] ?? false),
        ]);

        return back()->with('success', 'Video berjaya ditambah.');
    }

    public function update(Request $request, Video $video): RedirectResponse
    {
        $user = $request->user();
        $isSuperadmin = $user->hasRole('Superadmin');

        if (! $isSuperadmin && $video->organization_id !== $user->current_organization_id) {
            abort(403);
        }

        $data = $request->validate([
            'organization_id' => ['nullable', 'integer', 'exists:organizations,id'],
            'title' => ['required', 'string', 'max:255'],
            'youtube_url' => ['required', 'string', 'max:500'],
            'is_live' => ['boolean'],
        ]);

        $youtubeId = $this->extractYoutubeId($data['youtube_url']);

        if (! $youtubeId) {
            return back()->with('error', 'URL YouTube tidak sah.');
        }

        $video->update([
            'organization_id' => $isSuperadmin ? ($data['organization_id'] ?? null) : $user->current_organization_id,
            'title' => $data['title'],
            'youtube_url' => $data['youtube_url'],
            'youtube_id' => $youtubeId,
            'is_live' => (bool) ($data['is_live'] ?? false),
        ]);

        return back()->with('success', 'Video berjaya dikemas kini.');
    }

    public function destroy(Request $request, Video $video): RedirectResponse
    {
        $user = $request->user();
        $isSuperadmin = $user->hasRole('Superadmin');

        if (! $isSuperadmin && $video->organization_id !== $user->current_organization_id) {
            abort(403);
        }

        $video->delete();

        return back()->with('success', 'Video berjaya dipadam.');
    }

    public function memberIndex(Request $request): Response
    {
        $videos = $this->videos->list($request, $request->user());

        return Inertia::render('Member/Videos', [
            'videos' => $videos,
        ]);
    }

    private function extractYoutubeId(string $url): ?string
    {
        $patterns = [
            '/youtube\.com\/watch\?v=([a-zA-Z0-9_-]{11})/',
            '/youtu\.be\/([a-zA-Z0-9_-]{11})/',
            '/youtube\.com\/embed\/([a-zA-Z0-9_-]{11})/',
            '/youtube\.com\/shorts\/([a-zA-Z0-9_-]{11})/',
            '/youtube\.com\/live\/([a-zA-Z0-9_-]{11})/',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $url, $matches)) {
                return $matches[1];
            }
        }

        return null;
    }
}
