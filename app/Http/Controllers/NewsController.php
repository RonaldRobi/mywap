<?php

namespace App\Http\Controllers;

use App\Models\NewsCategory;
use App\Models\NewsPost;
use App\Models\NewsPostComment;
use App\Models\NewsPostReaction;
use App\Models\Organization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class NewsController extends Controller
{
    private const SUPERADMIN_ALLOWED_TARGET_SLUGS = ['wadah', 'abim', 'pkpim'];

    public function index(Request $request): Response
    {
        $user = $request->user();
        $categoryId = $request->integer('category_id');

        $categories = NewsCategory::query()
            ->where('is_active', true)
            ->orderBy('display_order')
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'icon']);

        $query = NewsPost::query()
            ->with(['category:id,name,slug', 'organization:id,name,slug', 'author:id,name'])
            ->with(['reactions' => fn ($q) => $q->where('user_id', $user->id)->select('id', 'news_post_id', 'user_id', 'reaction')])
            ->withCount([
                'reactions as likes_count' => fn ($q) => $q->where('reaction', 'like'),
                'reactions as dislikes_count' => fn ($q) => $q->where('reaction', 'dislike'),
                'comments as comments_count' => fn ($q) => $q->where('is_hidden', false),
            ])
            ->where('is_published', true)
            ->where(function ($q) use ($user) {
                if ($user->hasRole('Superadmin')) {
                    return;
                }

                $q->whereNull('organization_id')
                    ->orWhere('organization_id', $user->current_organization_id);
            })
            ->where(function ($q) {
                $q->whereNull('published_at')->orWhere('published_at', '<=', now());
            });

        if ($categoryId) {
            $query->where('news_category_id', $categoryId);
        }

        $posts = $query->latest('published_at')
            ->latest('id')
            ->paginate(12)
            ->withQueryString()
            ->through(fn (NewsPost $post) => $this->serializePostSummary($post));

        return Inertia::render('Info/Index', [
            'posts' => $posts,
            'categories' => $categories,
            'filters' => [
                'category_id' => $categoryId ?: null,
            ],
        ]);
    }

    public function show(Request $request, NewsPost $newsPost): Response
    {
        $user = $request->user();
        abort_unless($this->canViewPost($user, $newsPost), 404);

        $newsPost->load(['category:id,name,slug', 'organization:id,name,slug', 'author:id,name']);

        $likes = $newsPost->reactions()->where('reaction', 'like')->count();
        $dislikes = $newsPost->reactions()->where('reaction', 'dislike')->count();
        $myReaction = $newsPost->reactions()->where('user_id', $user->id)->value('reaction');

        $comments = $newsPost->comments()
            ->where('is_hidden', false)
            ->with('user:id,name')
            ->latest()
            ->take(100)
            ->get()
            ->map(fn (NewsPostComment $comment) => [
                'id' => $comment->id,
                'content' => $comment->content,
                'user_name' => $comment->user?->name ?? 'Ahli',
                'created_at' => $comment->created_at?->diffForHumans(),
            ]);

        return Inertia::render('Info/Show', [
            'post' => [
                'id' => $newsPost->id,
                'title' => $newsPost->title,
                'excerpt' => $newsPost->excerpt,
                'content' => $newsPost->content,
                'cover_image_path' => $newsPost->cover_image_path,
                'published_at' => $newsPost->published_at?->toDateTimeString(),
                'organization_name' => $newsPost->organization?->name ?? 'Semua Organisasi',
                'category' => $newsPost->category ? [
                    'id' => $newsPost->category->id,
                    'name' => $newsPost->category->name,
                ] : null,
                'author_name' => $newsPost->author?->name ?? '-',
                'likes_count' => $likes,
                'dislikes_count' => $dislikes,
                'my_reaction' => $myReaction,
            ],
            'comments' => $comments,
        ]);
    }

    public function react(Request $request, NewsPost $newsPost): RedirectResponse
    {
        $user = $request->user();
        abort_unless($this->canViewPost($user, $newsPost), 404);

        $data = $request->validate([
            'reaction' => ['required', 'in:like,dislike'],
        ]);

        $existing = NewsPostReaction::query()
            ->where('news_post_id', $newsPost->id)
            ->where('user_id', $user->id)
            ->first();

        if ($existing && $existing->reaction === $data['reaction']) {
            $existing->delete();
        } elseif ($existing) {
            $existing->update(['reaction' => $data['reaction']]);
        } else {
            NewsPostReaction::create([
                'news_post_id' => $newsPost->id,
                'user_id' => $user->id,
                'reaction' => $data['reaction'],
            ]);
        }

        return back()->with('success', 'Reaksi berjaya dikemas kini.');
    }

    public function storeComment(Request $request, NewsPost $newsPost): RedirectResponse
    {
        $user = $request->user();
        abort_unless($this->canViewPost($user, $newsPost), 404);

        $data = $request->validate([
            'content' => ['required', 'string', 'max:1500'],
        ]);

        NewsPostComment::create([
            'news_post_id' => $newsPost->id,
            'user_id' => $user->id,
            'content' => trim($data['content']),
            'is_hidden' => false,
        ]);

        return back()->with('success', 'Komen berjaya dihantar.');
    }

    public function manage(Request $request): Response
    {
        abort_unless($request->user()?->hasRole(['Superadmin', 'Admin']), 403);

        $user = $request->user()->load('organization');
        $isSuperadmin = $user->hasRole('Superadmin');

        $categories = NewsCategory::query()
            ->orderBy('display_order')
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'icon', 'is_active', 'display_order']);

        $posts = NewsPost::query()
            ->with(['category:id,name', 'organization:id,name', 'author:id,name'])
            ->when(! $isSuperadmin, function ($query) use ($user) {
                $query->where(function ($q) use ($user) {
                    $q->whereNull('organization_id')
                        ->orWhere('organization_id', $user->current_organization_id);
                });
            })
            ->latest('id')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (NewsPost $post) => [
                'id' => $post->id,
                'title' => $post->title,
                'excerpt' => $post->excerpt,
                'content' => $post->content,
                'category_id' => $post->news_category_id,
                'organization_id' => $post->organization_id,
                'organization_name' => $post->organization?->name ?? 'Semua Organisasi',
                'category_name' => $post->category?->name ?? 'Umum',
                'cover_image_path' => $post->cover_image_path,
                'is_published' => (bool) $post->is_published,
                'published_at' => $post->published_at?->toDateTimeString(),
                'author_name' => $post->author?->name ?? '-',
            ]);

        $targetOrganizations = $isSuperadmin
            ? Organization::query()
                ->whereIn('slug', self::SUPERADMIN_ALLOWED_TARGET_SLUGS)
                ->orderBy('min_age')
                ->get(['id', 'name', 'slug'])
            : collect([[
                'id' => $user->current_organization_id,
                'name' => $user->organization?->name ?? 'Organisasi Sendiri',
                'slug' => $user->organization?->slug ?? 'org',
            ]]);

        return Inertia::render('Admin/InfoManage', [
            'isSuperadmin' => $isSuperadmin,
            'defaultOrganizationId' => $user->current_organization_id,
            'categories' => $categories,
            'posts' => $posts,
            'targetOrganizations' => $targetOrganizations,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->hasRole(['Superadmin', 'Admin']), 403);

        $user = $request->user();
        $isSuperadmin = $user->hasRole('Superadmin');

        $data = $request->validate([
            'organization_id' => ['nullable', 'integer', 'exists:organizations,id'],
            'news_category_id' => ['nullable', 'integer', 'exists:news_categories,id'],
            'title' => ['required', 'string', 'max:255'],
            'excerpt' => ['nullable', 'string', 'max:500'],
            'content' => ['required', 'string', 'max:20000'],
            'cover_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'is_published' => ['nullable', 'boolean'],
        ]);

        $organizationId = $this->resolvePostOrganizationId($user, $data['organization_id'] ?? null, $isSuperadmin);

        $coverImagePath = null;
        if ($request->hasFile('cover_image')) {
            $coverImagePath = '/storage/' . ltrim($request->file('cover_image')->store('info', 'public'), '/');
        }

        NewsPost::create([
            'author_id' => $user->id,
            'organization_id' => $organizationId,
            'news_category_id' => $data['news_category_id'] ?? null,
            'title' => $data['title'],
            'excerpt' => $data['excerpt'] ?? null,
            'content' => $data['content'],
            'cover_image_path' => $coverImagePath,
            'is_published' => (bool) ($data['is_published'] ?? true),
            'published_at' => (bool) ($data['is_published'] ?? true) ? now() : null,
        ]);

        return back()->with('success', 'Info terkini berjaya dicipta.');
    }

    public function update(Request $request, NewsPost $newsPost): RedirectResponse
    {
        abort_unless($request->user()?->hasRole(['Superadmin', 'Admin']), 403);
        $this->authorizeManagePost($request->user(), $newsPost);

        $user = $request->user();
        $isSuperadmin = $user->hasRole('Superadmin');

        $data = $request->validate([
            'organization_id' => ['nullable', 'integer', 'exists:organizations,id'],
            'news_category_id' => ['nullable', 'integer', 'exists:news_categories,id'],
            'title' => ['required', 'string', 'max:255'],
            'excerpt' => ['nullable', 'string', 'max:500'],
            'content' => ['required', 'string', 'max:20000'],
            'cover_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'is_published' => ['nullable', 'boolean'],
        ]);

        $organizationId = $this->resolvePostOrganizationId($user, $data['organization_id'] ?? null, $isSuperadmin);

        $coverImagePath = $newsPost->cover_image_path;
        if ($request->hasFile('cover_image')) {
            $oldPath = ltrim(str_replace('/storage/', '', parse_url($newsPost->cover_image_path ?? '', PHP_URL_PATH) ?? ''), '/');
            if ($oldPath !== '' && Storage::disk('public')->exists($oldPath)) {
                Storage::disk('public')->delete($oldPath);
            }

            $coverImagePath = '/storage/' . ltrim($request->file('cover_image')->store('info', 'public'), '/');
        }

        $wasPublished = (bool) $newsPost->is_published;
        $isPublished = (bool) ($data['is_published'] ?? false);

        $newsPost->update([
            'organization_id' => $organizationId,
            'news_category_id' => $data['news_category_id'] ?? null,
            'title' => $data['title'],
            'excerpt' => $data['excerpt'] ?? null,
            'content' => $data['content'],
            'cover_image_path' => $coverImagePath,
            'is_published' => $isPublished,
            'published_at' => $isPublished
                ? ($wasPublished ? $newsPost->published_at : now())
                : null,
        ]);

        return back()->with('success', 'Info terkini berjaya dikemas kini.');
    }

    public function destroy(Request $request, NewsPost $newsPost): RedirectResponse
    {
        abort_unless($request->user()?->hasRole(['Superadmin', 'Admin']), 403);
        $this->authorizeManagePost($request->user(), $newsPost);

        $oldPath = ltrim(str_replace('/storage/', '', parse_url($newsPost->cover_image_path ?? '', PHP_URL_PATH) ?? ''), '/');
        if ($oldPath !== '' && Storage::disk('public')->exists($oldPath)) {
            Storage::disk('public')->delete($oldPath);
        }

        $newsPost->delete();

        return back()->with('success', 'Info terkini berjaya dipadam.');
    }

    public function storeCategory(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->hasRole(['Superadmin', 'Admin']), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:news_categories,name'],
            'icon' => ['nullable', 'string', 'max:30'],
        ]);

        NewsCategory::create([
            'name' => $data['name'],
            'slug' => Str::slug($data['name']),
            'icon' => $data['icon'] ?? null,
            'is_active' => true,
            'display_order' => (NewsCategory::max('display_order') ?? 0) + 1,
        ]);

        return back()->with('success', 'Kategori info terkini berjaya ditambah.');
    }

    private function canViewPost($user, NewsPost $newsPost): bool
    {
        $isPublished = (bool) $newsPost->is_published;
        $publishedAt = $newsPost->published_at;
        $isPublishedNow = $isPublished && (is_null($publishedAt) || $publishedAt->lte(now()));

        if (! $isPublishedNow) {
            return $user->hasRole(['Superadmin', 'Admin']) && $this->canAdminManagePost($user, $newsPost);
        }

        if ($user->hasRole('Superadmin')) {
            return true;
        }

        return is_null($newsPost->organization_id)
            || (int) $newsPost->organization_id === (int) $user->current_organization_id;
    }

    private function serializePostSummary(NewsPost $post): array
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

    private function resolvePostOrganizationId($user, ?int $submittedOrganizationId, bool $isSuperadmin): ?int
    {
        if ($isSuperadmin) {
            if (is_null($submittedOrganizationId)) {
                return null;
            }

            $isAllowed = Organization::query()
                ->where('id', $submittedOrganizationId)
                ->whereIn('slug', self::SUPERADMIN_ALLOWED_TARGET_SLUGS)
                ->exists();

            abort_unless($isAllowed, 403);

            return $submittedOrganizationId ?: null;
        }

        if (is_null($submittedOrganizationId)) {
            return null;
        }

        abort_if((int) $submittedOrganizationId !== (int) $user->current_organization_id, 403);

        return (int) $submittedOrganizationId;
    }

    private function authorizeManagePost($user, NewsPost $newsPost): void
    {
        abort_unless($this->canAdminManagePost($user, $newsPost), 403);
    }

    private function canAdminManagePost($user, NewsPost $newsPost): bool
    {
        if ($user->hasRole('Superadmin')) {
            return true;
        }

        return is_null($newsPost->organization_id)
            || (int) $newsPost->organization_id === (int) $user->current_organization_id;
    }
}
