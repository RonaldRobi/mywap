<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\LibraryItem;
use Inertia\Inertia;
use Inertia\Response;

class InformationHubController extends Controller
{
    public function announcements(): Response
    {
        $announcements = Announcement::query()
            ->where(function ($query) {
                $query->whereNull('published_at')
                    ->orWhere('published_at', '<=', now());
            })
            ->orderByDesc('is_pinned')
            ->orderByDesc('published_at')
            ->latest('id')
            ->take(20)
            ->get()
            ->map(fn (Announcement $announcement) => [
                'id' => $announcement->id,
                'title' => $announcement->title,
                'content' => $announcement->content,
                'is_pinned' => $announcement->is_pinned,
                'published_at' => $announcement->published_at?->toDateTimeString(),
                'published_human' => $announcement->published_at?->locale('ms')->isoFormat('D MMM YYYY, h:mm A'),
            ]);

        return Inertia::render('Member/Announcements', [
            'announcements' => $announcements,
        ]);
    }

    public function library(): Response
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
