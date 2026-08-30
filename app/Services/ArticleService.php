<?php

namespace App\Services;

use App\Models\Article;
use App\Models\ArticleCategory;
use App\Models\ArticleComment;
use App\Models\ArticleReaction;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * ArticleService
 *
 * Logik tunggal untuk domain Artikel — dikongsi oleh WebController (Inertia)
 * dan ApiController (JSON) supaya web & Flutter tidak drift.
 * Rujuk docs/FLUTTER_PLAN.md §8.
 */
class ArticleService
{
    /**
     * Serialize satu artikel kepada bentuk ringkas untuk senarai web & API.
     */
    public function serializeSummary(Article $article): array
    {
        return [
            'id' => $article->id,
            'title' => $article->title,
            'slug' => $article->slug,
            'cover_image' => $article->cover_image_path,
            'excerpt' => Str::limit(strip_tags($article->content), 100),
            'author_name' => $article->author?->name ?? 'Admin',
            'published_date' => $article->published_at ? $article->published_at->format('d M Y') : $article->created_at->format('d M Y'),
            'public_url' => route('articles.show', $article->slug),
            'is_featured' => $article->is_featured,
            'categories' => $article->categories->map(fn ($c) => ['id' => $c->id, 'name' => $c->name])->values(),
        ];
    }

    public function categories(): Collection
    {
        return ArticleCategory::orderBy('name')->get(['id', 'name', 'slug']);
    }

    /**
     * Senarai artikel diterbitkan (berpagina) untuk API.
     */
    public function list(Request $request): LengthAwarePaginator
    {
        return $this->publishedQuery()
            ->paginate(self::perPage($request))
            ->withQueryString()
            ->through(fn (Article $article) => $this->serializeSummary($article));
    }

    /**
     * Senarai penuh artikel diterbitkan (tanpa pagination) untuk web index.
     */
    public function listAll(): Collection
    {
        return $this->publishedQuery()
            ->limit(60)
            ->get()
            ->map(fn (Article $article) => $this->serializeSummary($article));
    }

    public function featured(): Collection
    {
        return Cache::remember('articles.featured', 300, function () {
            return $this->publishedQuery()
                ->where('is_featured', true)
                ->limit(8)
                ->get()
                ->map(fn (Article $article) => $this->serializeSummary($article))
                ->values();
        });
    }

    /**
     * Payload penuh untuk halaman/endpoint show artikel.
     */
    public function showDetail(Article $article, ?User $user = null, ?string $sessionId = null): array
    {
        $article->loadMissing(['author' => fn ($q) => $q->withoutGlobalScopes()->select('id', 'name'), 'organization:id,name,slug', 'categories', 'tags', 'media']);

        $likes = $article->reactions()->where('reaction', 'like')->count();
        $dislikes = $article->reactions()->where('reaction', 'dislike')->count();
        $myReaction = $article->reactions()
            ->where(function ($q) use ($user, $sessionId) {
                if ($user) {
                    $q->where('user_id', $user->id);
                } else {
                    $q->where('session_id', $sessionId);
                }
            })->value('reaction');

        $comments = $article->comments()
            ->where('is_hidden', false)
            ->with(['user' => fn ($q) => $q->withoutGlobalScopes()->select('id', 'name')])
            ->latest()
            ->take(100)
            ->get()
            ->map(fn ($comment) => [
                'id' => $comment->id,
                'content' => $comment->content,
                'user_name' => $comment->user?->name ?? $comment->anonymous_name ?? 'Anonim',
                'created_at' => $comment->created_at?->diffForHumans(),
            ])
            ->values();

        return [
            'article' => [
                'id' => $article->id,
                'slug' => $article->slug,
                'title' => $article->title,
                'excerpt' => $article->excerpt,
                'content' => $article->content,
                'cover_image_path' => $article->cover_image_path,
                'published_at' => $article->published_at?->toDateTimeString(),
                'organization_name' => $article->organization?->name ?? 'Semua Organisasi',
                'author_name' => $article->author?->name ?? '-',
                'likes_count' => $likes,
                'dislikes_count' => $dislikes,
                'my_reaction' => $myReaction,
                'categories' => $article->categories->map(fn ($c) => ['id' => $c->id, 'name' => $c->name])->values(),
                'tags' => $article->tags->map(fn ($t) => ['id' => $t->id, 'name' => $t->name])->values(),
                'gallery' => $article->media->map(fn ($m) => ['id' => $m->id, 'path' => $m->path, 'caption' => $m->caption])->values(),
            ],
            'comments' => $comments,
        ];
    }

    /**
     * Togol/ubah reaksi user (atau session anonim) terhadap satu artikel.
     * Pulangkan state reaksi terkini.
     */
    public function react(Article $article, string $reaction, ?User $user = null, ?string $sessionId = null): array
    {
        $existing = ArticleReaction::query()
            ->where('article_id', $article->id)
            ->where(function ($q) use ($user, $sessionId) {
                if ($user) {
                    $q->where('user_id', $user->id);
                } else {
                    $q->where('session_id', $sessionId);
                }
            })
            ->first();

        if ($existing && $existing->reaction === $reaction) {
            $existing->delete();
            $current = null;
        } elseif ($existing) {
            $existing->update(['reaction' => $reaction]);
            $current = $reaction;
        } else {
            ArticleReaction::create([
                'article_id' => $article->id,
                'user_id' => $user?->id,
                'session_id' => $user ? null : $sessionId,
                'reaction' => $reaction,
            ]);
            $current = $reaction;
        }

        return [
            'article_id' => $article->id,
            'reaction' => $current,
            'likes_count' => $article->reactions()->where('reaction', 'like')->count(),
            'dislikes_count' => $article->reactions()->where('reaction', 'dislike')->count(),
        ];
    }

    public function addComment(Article $article, string $content, ?User $user = null, ?string $anonymousName = null): array
    {
        $comment = ArticleComment::create([
            'article_id' => $article->id,
            'user_id' => $user?->id,
            'anonymous_name' => $user ? null : ($anonymousName ?? 'Anonim'),
            'content' => trim($content),
            'is_hidden' => false,
        ]);

        return [
            'id' => $comment->id,
            'content' => $comment->content,
            'user_name' => $user?->name ?? $comment->anonymous_name ?? 'Anonim',
            'created_at' => $comment->created_at?->diffForHumans(),
        ];
    }

    /**
     * Pastikan per_page hanya nilai yang dibenarkan (12/25/50/100 default 12).
     */
    public static function perPage(Request $request, int $default = 12): int
    {
        $perPage = (int) $request->input('per_page', $default);

        return in_array($perPage, [12, 25, 50, 100]) ? $perPage : $default;
    }

    private function publishedQuery()
    {
        return Article::with([
            'author' => fn ($q) => $q->withoutGlobalScopes()->select('id', 'name'),
            'organization:id,name,slug',
            'categories',
            'tags',
        ])
            ->where('is_published', true)
            ->where(function ($query) {
                $query->whereNull('published_at')
                    ->orWhere('published_at', '<=', now());
            })
            ->latest('published_at');
    }
}
