<?php

namespace App\Services;

use App\Models\NewsCategory;
use App\Models\NewsPost;
use App\Models\NewsPostComment;
use App\Models\NewsPostReaction;
use App\Models\User;
use App\Models\UserBlock;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * NewsService
 *
 * Logik tunggal untuk domain Info Terkini (news) — dikongsi oleh WebController
 * (Inertia) dan ApiController (JSON) supaya web & Flutter tidak drift.
 * Rujuk docs/FLUTTER_PLAN.md §8.
 */
class NewsService
{
    /**
     * Serialize satu post kepada bentuk ringkas untuk senarai web & API.
     */
    public function serializeSummary(NewsPost $post): array
    {
        return [
            'id' => $post->id,
            'title' => $post->title,
            'slug' => $post->slug,
            'excerpt' => $post->excerpt,
            'cover_image_path' => $post->cover_image_path,
            'organization_name' => $post->organization?->name ?? 'Semua Organisasi',
            'category_name' => $post->category?->name ?? 'Umum',
            'published_at' => $post->published_at?->toDateTimeString(),
            'likes_count' => (int) ($post->likes_count ?? 0),
            'dislikes_count' => (int) ($post->dislikes_count ?? 0),
            'comments_count' => (int) ($post->comments_count ?? 0),
            'my_reaction' => $post->reactions->first()?->reaction,
        ];
    }

    /**
     * Kategori aktif (mengikut urutan paparan) untuk penapis web & API.
     */
    public function categories(): Collection
    {
        return NewsCategory::query()
            ->where('is_active', true)
            ->orderBy('display_order')
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'icon']);
    }

    /**
     * Senarai post diterbitkan mengikut skop organisasi user + penapis kategori.
     * Tetamu (user null) melihat semua post yang diterbitkan.
     */
    public function list(Request $request, ?User $user = null): LengthAwarePaginator
    {
        $categoryId = $request->integer('category_id');

        $relations = ['category:id,name,slug', 'organization:id,name,slug', 'author' => fn ($q) => $q->withoutGlobalScope(\App\Models\Scopes\OrganizationScope::class)->select('id', 'name')];

        if ($user) {
            $relations['reactions'] = fn ($q) => $q->where('user_id', $user->id)->select('id', 'news_post_id', 'user_id', 'reaction');
        }

        $query = NewsPost::query()
            ->with($relations)
            ->withCount([
                'reactions as likes_count' => fn ($q) => $q->where('reaction', 'like'),
                'reactions as dislikes_count' => fn ($q) => $q->where('reaction', 'dislike'),
                'comments as comments_count' => fn ($q) => $q->where('is_hidden', false),
            ])
            ->where('is_published', true)
            ->where(function ($q) {
                $q->whereNull('published_at')->orWhere('published_at', '<=', now());
            });

        if ($user && ! $user->hasRole('Superadmin')) {
            $query->where(function ($q) use ($user) {
                $q->whereNull('organization_id')
                    ->orWhere('organization_id', $user->current_organization_id);
            });
        }

        if ($categoryId) {
            $query->where('news_category_id', $categoryId);
        }

        return $query->latest('published_at')
            ->latest('id')
            ->paginate(self::perPage($request))
            ->withQueryString()
            ->through(fn (NewsPost $post) => $this->serializeSummary($post));
    }

    /**
     * Payload penuh untuk halaman/endpoint show post.
     * Tetamu (user null) melihat kandungan + kiraan komen, tetapi bukan senarai
     * komen (komen adalah ciri ahli).
     */
    public function showDetail(NewsPost $post, ?User $user = null): array
    {
        $post->loadMissing(['category:id,name,slug', 'organization:id,name,slug', 'author' => fn ($q) => $q->withoutGlobalScope(\App\Models\Scopes\OrganizationScope::class)->select('id', 'name')]);

        $likes = $post->reactions()->where('reaction', 'like')->count();
        $dislikes = $post->reactions()->where('reaction', 'dislike')->count();
        $myReaction = $user ? $post->reactions()->where('user_id', $user->id)->value('reaction') : null;
        $commentsCount = $post->comments()->where('is_hidden', false)->count();

        $blockedIds = $user
            ? UserBlock::query()->where('blocker_id', $user->id)->pluck('blocked_id')->all()
            : [];

        $comments = $user
            ? $post->comments()
                ->where('is_hidden', false)
                ->when($blockedIds !== [], fn ($q) => $q->whereNotIn('user_id', $blockedIds))
                ->with(['user' => fn ($q) => $q->withoutGlobalScope(\App\Models\Scopes\OrganizationScope::class)->select('id', 'name')])
                ->latest()
                ->take(100)
                ->get()
                ->map(fn (NewsPostComment $comment) => [
                    'id' => $comment->id,
                    'user_id' => $comment->user_id,
                    'content' => $comment->content,
                    'user_name' => $comment->user?->name ?? 'Ahli',
                    'created_at' => $comment->created_at?->diffForHumans(),
                ])
                ->values()
            : collect();

        $canEdit = $user
            && ($user->hasRole('Superadmin')
                || ($user->hasRole('Admin')
                    && (is_null($post->organization_id)
                        || (int) $post->organization_id === (int) $user->current_organization_id)));

        return [
            'post' => [
                'id' => $post->id,
                'title' => $post->title,
                'excerpt' => $post->excerpt,
                'content' => $post->content,
                'cover_image_path' => $post->cover_image_path,
                'published_at' => $post->published_at?->toDateTimeString(),
                'organization_name' => $post->organization?->name ?? 'Semua Organisasi',
                'category' => $post->category ? [
                    'id' => $post->category->id,
                    'name' => $post->category->name,
                ] : null,
                'author_name' => $post->author?->name ?? '-',
                'likes_count' => $likes,
                'dislikes_count' => $dislikes,
                'comments_count' => $commentsCount,
                'my_reaction' => $myReaction,
                'can_edit' => $canEdit,
            ],
            'comments' => $comments,
        ];
    }

    /**
     * Togol/ubah reaksi user terhadap satu post. Pulangkan state reaksi terkini.
     */
    public function react(User $user, NewsPost $post, string $reaction): array
    {
        $existing = NewsPostReaction::query()
            ->where('news_post_id', $post->id)
            ->where('user_id', $user->id)
            ->first();

        if ($existing && $existing->reaction === $reaction) {
            $existing->delete();
            $current = null;
        } elseif ($existing) {
            $existing->update(['reaction' => $reaction]);
            $current = $reaction;
        } else {
            NewsPostReaction::create([
                'news_post_id' => $post->id,
                'user_id' => $user->id,
                'reaction' => $reaction,
            ]);
            $current = $reaction;
        }

        return [
            'post_id' => $post->id,
            'reaction' => $current,
            'likes_count' => $post->reactions()->where('reaction', 'like')->count(),
            'dislikes_count' => $post->reactions()->where('reaction', 'dislike')->count(),
        ];
    }

    public function addComment(User $user, NewsPost $post, string $content): array
    {
        $comment = NewsPostComment::create([
            'news_post_id' => $post->id,
            'user_id' => $user->id,
            'content' => trim($content),
            'is_hidden' => false,
        ]);

        return [
            'id' => $comment->id,
            'content' => $comment->content,
            'user_name' => $user->name,
            'created_at' => $comment->created_at?->diffForHumans(),
        ];
    }

    /**
     * Bolehkah user melihat post ini (diterbitkan & dalam skop)?
     * Tetamu (user null) boleh melihat mana-mana post yang diterbitkan.
     */
    public function canViewPost(?User $user, NewsPost $post): bool
    {
        $isPublished = (bool) $post->is_published;
        $publishedAt = $post->published_at;
        $isPublishedNow = $isPublished && (is_null($publishedAt) || $publishedAt->lte(now()));

        if (! $isPublishedNow) {
            return $user !== null
                && $user->hasRole(['Superadmin', 'Admin'])
                && $this->canAdminManagePost($user, $post);
        }

        if ($user === null) {
            return true;
        }

        if ($user->hasRole('Superadmin')) {
            return true;
        }

        return is_null($post->organization_id)
            || (int) $post->organization_id === (int) $user->current_organization_id;
    }

    public function authorizeManagePost(User $user, NewsPost $post): void
    {
        abort_unless($this->canAdminManagePost($user, $post), 403);
    }

    public function canAdminManagePost(User $user, NewsPost $post): bool
    {
        if ($user->hasRole('Superadmin')) {
            return true;
        }

        return is_null($post->organization_id)
            || (int) $post->organization_id === (int) $user->current_organization_id;
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
