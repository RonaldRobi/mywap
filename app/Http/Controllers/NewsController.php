<?php

namespace App\Http\Controllers;

use App\Models\NewsCategory;
use App\Models\NewsPost;
use App\Models\Organization;
use App\Services\NewsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class NewsController extends Controller
{
    private const SUPERADMIN_ALLOWED_TARGET_SLUGS = ['wadah', 'abim', 'pkpim'];

    public function __construct(private readonly NewsService $news) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $categoryId = $request->integer('category_id');

        $posts = $this->news->list($request, $user);

        return Inertia::render('Info/Index', [
            'posts' => $posts,
            'categories' => $this->news->categories(),
            'filters' => [
                'category_id' => $categoryId ?: null,
            ],
        ]);
    }

    public function show(Request $request, NewsPost $newsPost): Response
    {
        $user = $request->user();
        abort_unless($this->news->canViewPost($user, $newsPost), 404);

        $detail = $this->news->showDetail($newsPost, $user);

        return Inertia::render('Info/Show', [
            'post' => $detail['post'],
            'comments' => $detail['comments'],
        ]);
    }

    public function react(Request $request, NewsPost $newsPost): RedirectResponse
    {
        $user = $request->user();
        abort_unless($this->news->canViewPost($user, $newsPost), 404);

        $data = $request->validate([
            'reaction' => ['required', 'in:like,dislike'],
        ]);

        $this->news->react($user, $newsPost, $data['reaction']);

        return back()->with('success', 'Reaksi berjaya dikemas kini.');
    }

    public function storeComment(Request $request, NewsPost $newsPost): RedirectResponse
    {
        $user = $request->user();
        abort_unless($this->news->canViewPost($user, $newsPost), 404);

        $data = $request->validate([
            'content' => ['required', 'string', 'max:1500'],
        ]);

        $this->news->addComment($user, $newsPost, $data['content']);

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
            $coverImagePath = '/storage/'.ltrim($request->file('cover_image')->store('info', 'public'), '/');
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
        $this->news->authorizeManagePost($request->user(), $newsPost);

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

            $coverImagePath = '/storage/'.ltrim($request->file('cover_image')->store('info', 'public'), '/');
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
        $this->news->authorizeManagePost($request->user(), $newsPost);

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

    public function destroyCategory(Request $request, NewsCategory $category): RedirectResponse
    {
        abort_unless($request->user()?->hasRole(['Superadmin', 'Admin']), 403);

        $category->delete();

        return back()->with('success', 'Kategori info terkini berjaya dipadam.');
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
}
