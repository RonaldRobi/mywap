<?php

namespace App\Services;

use App\Models\Announcement;
use App\Models\AppSetting;
use App\Models\LibraryItem;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

/**
 * MemberCoreService
 *
 * Logik tunggal untuk domain Member core (kad ahli + status yuran +
 * pengumuman + pustaka) — dikongsi oleh WebController (Inertia) dan
 * ApiController (JSON) supaya web & Flutter tidak drift.
 * Rujuk docs/FLUTTER_PLAN.md §4.
 */
class MemberCoreService
{
    public function __construct(private readonly FeeService $fees) {}

    /**
     * Payload kad ahli — bentuk konsisten untuk web & API.
     * $includeQrValue menambah card['qr_value'] (nilai yang dikodkan dalam
     * QR) supaya klien mobile boleh jana QR sendiri.
     */
    public function card(User $user, bool $includeQrValue = false): array
    {
        $user->loadMissing('organization');
        $setting = Schema::hasTable('app_settings')
            ? AppSetting::query()->first()
            : null;

        $privateUrl = route('member.card');
        $publicUrl = $user->member_no ? route('public.card', ['memberNo' => $user->member_no]) : null;

        $card = [
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
        ];

        if ($includeQrValue) {
            $card['qr_value'] = $publicUrl ?? $privateUrl;
        }

        return [
            'card' => $card,
            'qrPrivate' => $this->generateQrSvg($privateUrl),
            'qrPublic' => $publicUrl ? $this->generateQrSvg($publicUrl) : null,
        ];
    }

    /**
     * Status yuran ahli untuk kad / skrin fee.
     */
    public function feeStatus(User $user): array
    {
        $user->loadMissing('organization');

        return [
            'status' => $this->fees->getStatus($user),
            'fee_amount' => (float) ($user->organization?->fee_amount ?? 50.00),
        ];
    }

    /**
     * Senarai pengumuman (take 20) — bentuk sama untuk web & API.
     * Reaction/read user dieager-load supaya tiada N+1.
     */
    public function announcements(User $user): Collection
    {
        return Announcement::query()
            ->with(['author:id,name', 'images'])
            ->withCount([
                'reactions as likes_count' => fn ($q) => $q->where('reaction', 'like'),
                'reads as reads_count',
            ])
            ->with([
                'reactions' => fn ($q) => $q->where('user_id', $user->id),
                'reads' => fn ($q) => $q->where('user_id', $user->id),
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
            ->map(function (Announcement $announcement) {
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
                    'user_reaction' => $announcement->reactions->first()?->reaction,
                    'is_read' => (bool) $announcement->reads->first(),
                    'images' => $announcement->images->map(fn ($img) => [
                        'id' => $img->id,
                        'url' => $img->imageUrl(),
                        'caption' => $img->caption,
                    ]),
                ];
            })
            ->values();
    }

    /**
     * Toggle reaksi 'like' — null bila dibuang.
     */
    public function toggleReaction(User $user, Announcement $announcement): ?string
    {
        $existing = $announcement->reactions()
            ->where('user_id', $user->id)
            ->first();

        if ($existing) {
            $existing->delete();

            return null;
        }

        $announcement->reactions()->create([
            'user_id' => $user->id,
            'reaction' => 'like',
        ]);

        return 'like';
    }

    public function markRead(User $user, Announcement $announcement): void
    {
        $announcement->reads()->updateOrCreate(
            ['user_id' => $user->id],
            ['read_at' => now()]
        );
    }

    public function library(): array
    {
        return LibraryItem::query()
            ->latest('id')
            ->limit(200)
            ->get()
            ->map(fn (LibraryItem $item) => [
                'id' => $item->id,
                'title' => $item->title,
                'description' => $item->description,
                'file_path' => $item->file_path,
                'cover_image_path' => $item->cover_image_path,
                'category' => $item->category,
            ])
            ->all();
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
