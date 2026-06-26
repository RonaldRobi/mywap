<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\AnnouncementReaction;
use App\Models\AnnouncementRead;
use App\Models\LibraryItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class InformationHubController extends Controller
{
    public function announcements(Request $request): Response
    {
        $user = $request->user();
        $orgId = $user->current_organization_id;

        $announcements = Announcement::query()
            ->with(['author:id,name', 'images'])
            ->withCount([
                'reactions as likes_count' => fn ($q) => $q->where('reaction', 'like'),
                'reads as reads_count',
            ])
            ->where(function ($query) {
                $query->whereNull('published_at')
                    ->orWhere('published_at', '<=', now());
            })
            ->orderByDesc('is_pinned')
            ->orderByDesc('published_at')
            ->latest('id')
            ->take(20)
            ->get()
            ->map(function (Announcement $announcement) use ($user) {
                $userReaction = $announcement->reactions()
                    ->where('user_id', $user->id)
                    ->first();

                $userRead = $announcement->reads()
                    ->where('user_id', $user->id)
                    ->first();

                return [
                    'id' => $announcement->id,
                    'title' => $announcement->title,
                    'content' => $announcement->content,
                    'is_pinned' => $announcement->is_pinned,
                    'published_at' => $announcement->published_at?->toDateTimeString(),
                    'published_human' => $announcement->published_at?->locale('ms')->isoFormat('D MMM YYYY, h:mm A'),
                    'cover_image_url' => $announcement->coverImageUrl(),
                    'author_name' => $announcement->author?->name,
                    'likes_count' => (int) $announcement->likes_count,
                    'reads_count' => (int) $announcement->reads_count,
                    'user_reaction' => $userReaction?->reaction,
                    'is_read' => (bool) $userRead,
                    'images' => $announcement->images->map(fn ($img) => [
                        'id' => $img->id,
                        'url' => $img->imageUrl(),
                        'caption' => $img->caption,
                    ]),
                ];
            });

        return Inertia::render('Member/Announcements', [
            'announcements' => $announcements,
        ]);
    }

    public function react(Request $request, Announcement $announcement): RedirectResponse
    {
        $user = $request->user();

        $existing = AnnouncementReaction::where('announcement_id', $announcement->id)
            ->where('user_id', $user->id)
            ->first();

        if ($existing) {
            $existing->delete();
        } else {
            AnnouncementReaction::create([
                'announcement_id' => $announcement->id,
                'user_id' => $user->id,
                'reaction' => 'like',
            ]);
        }

        return back();
    }

    public function markRead(Request $request, Announcement $announcement): RedirectResponse
    {
        $user = $request->user();

        AnnouncementRead::updateOrCreate(
            [
                'announcement_id' => $announcement->id,
                'user_id' => $user->id,
            ],
            [
                'read_at' => now(),
            ]
        );

        return back();
    }

    public function library(Request $request): Response
    {
        $libraryItems = LibraryItem::query()
            ->latest('id')
            ->get()
            ->map(fn (LibraryItem $item) => [
                'id' => $item->id,
                'title' => $item->title,
                'description' => $item->description,
                'file_path' => $item->file_path,
                'cover_image_path' => $item->cover_image_path,
                'category' => $item->category,
            ]);

        return Inertia::render('Member/Library', [
            'libraryItems' => $libraryItems,
        ]);
    }
}
