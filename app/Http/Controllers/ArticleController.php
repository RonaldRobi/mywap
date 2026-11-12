<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\ArticleCategory;
use App\Models\ArticleMedia;
use App\Models\ArticleTag;
use App\Models\Organization;
use App\Services\ArticleService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;

class ArticleController extends Controller
{
    public function __construct(private readonly ArticleService $articles) {}

    public function manage(Request $request)
    {
        $user = $request->user();
        $branchId = $user->isBranchAdmin() ? $user->managedBranchId() : null;

        $articles = Article::with(['author', 'organization'])
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->when(! $branchId && ! $user->isSuperadmin(), fn ($q) => $q->where('organization_id', $user->current_organization_id))
            ->latest('published_at')
            ->paginate(10);

        return Inertia::render('Admin/ArticleManage', [
            'articles' => $articles,
            'organizations' => Organization::all(),
        ]);
    }

    public function adminIndex(Request $request)
    {
        $user = $request->user();
        $branchId = $user->isBranchAdmin() ? $user->managedBranchId() : null;

        $articles = Article::with(['author', 'organization', 'categories', 'tags'])
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->when(! $branchId && ! $user->isSuperadmin(), fn ($q) => $q->where('organization_id', $user->current_organization_id))
            ->latest('created_at')
            ->paginate(15);

        return Inertia::render('Admin/Articles/Index', [
            'articles' => $articles,
            'categories' => ArticleCategory::orderBy('name')->get(),
        ]);
    }

    public function adminCreate(Request $request)
    {
        $user = $request->user();

        return Inertia::render('Admin/Articles/Create', [
            'organizations' => $this->manageableOrganizations($user),
            'categories' => ArticleCategory::orderBy('name')->get(),
            'tags' => ArticleTag::orderBy('name')->get(),
        ]);
    }

    public function adminEdit(Request $request, Article $article)
    {
        $user = $request->user();
        $this->authorizeArticleAccess($user, $article);

        $article->load(['author', 'organization', 'categories', 'tags', 'media', 'meta']);

        return Inertia::render('Admin/Articles/Edit', [
            'article' => $article,
            'organizations' => $this->manageableOrganizations($user),
            'categories' => ArticleCategory::orderBy('name')->get(),
            'tags' => ArticleTag::orderBy('name')->get(),
        ]);
    }

    public function index()
    {
        $categories = $this->articles->categories();

        $articles = $this->articles->listAll();

        $featured = $articles->where('is_featured', true)->values();

        return Inertia::render('Article/Index', [
            'articles' => $articles,
            'featured' => $featured,
            'categories' => $categories,
        ]);
    }

    public function show(Request $request, Article $article)
    {
        abort_if(! $article->is_published, 404);
        abort_if($article->published_at && $article->published_at->isFuture(), 404);

        $detail = $this->articles->showDetail($article, $request->user(), $request->session()->getId());

        return Inertia::render('Article/Show', [
            'article' => $detail['article'],
            'comments' => $detail['comments'],
        ]);
    }

    public function react(Request $request, Article $article)
    {
        abort_if(! $article->is_published, 404);

        $data = $request->validate([
            'reaction' => ['required', 'in:like,dislike'],
        ]);

        $this->articles->react($article, $data['reaction'], $request->user(), $request->session()->getId());

        return back()->with('success', 'Reaksi berjaya dikemas kini.');
    }

    public function storeComment(Request $request, Article $article)
    {
        abort_if(! $article->is_published, 404);

        $data = $request->validate([
            'content' => ['required', 'string', 'max:1500'],
            'anonymous_name' => ['nullable', 'string', 'max:50'],
        ]);

        $this->articles->addComment(
            $article,
            $data['content'],
            $request->user(),
            $data['anonymous_name'] ?? null
        );

        return back()->with('success', 'Komen berjaya dihantar.');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'organization_id' => 'nullable|exists:organizations,id',
            'excerpt' => 'nullable|string',
            'content' => 'required|string',
            'is_published' => 'boolean',
            'is_featured' => 'boolean',
            'cover_image' => 'nullable|image|max:2048',
            'categories' => 'nullable|array',
            'categories.*' => 'exists:article_categories,id',
            'tags' => 'nullable|string',
            'gallery' => 'nullable|array',
            'gallery.*' => 'image|max:4096',
        ]);

        if ($request->hasFile('cover_image')) {
            $path = $request->file('cover_image')->store('articles', 'public');
            $validated['cover_image_path'] = '/storage/'.$path;
        }
        unset($validated['cover_image']);

        $user = $request->user();
        $validated['organization_id'] = $user->isSuperadmin()
            ? ($validated['organization_id'] ?? null)
            : $user->current_organization_id;
        $validated['branch_id'] = $user->isBranchAdmin() ? $user->managedBranchId() : null;

        $validated['author_id'] = auth()->id();
        $validated['slug'] = Str::slug($validated['title']).'-'.uniqid();
        $validated['is_published'] = filter_var($request->input('is_published', false), FILTER_VALIDATE_BOOLEAN);
        $validated['is_featured'] = $request->boolean('is_featured', false);

        if ($validated['is_published']) {
            $validated['published_at'] = now();
        }

        $categoryIds = $request->input('categories', []);
        unset($validated['categories']);

        $tagNames = $request->input('tags', '');
        unset($validated['tags']);

        $galleryFiles = $request->file('gallery', []);
        unset($validated['gallery']);

        $article = Article::create($validated);

        if ($categoryIds) {
            $article->categories()->sync($categoryIds);
        }

        if ($tagNames) {
            $tagIds = $this->syncTags($tagNames);
            $article->tags()->sync($tagIds);
        }

        foreach ($galleryFiles as $file) {
            $path = $file->store('articles/gallery', 'public');
            $article->media()->create([
                'path' => '/storage/'.$path,
                'type' => 'image',
            ]);
        }

        return redirect()->route('admin.articles.edit', $article)
            ->with('success', 'Artikel berjaya dicipta.');
    }

    public function update(Request $request, Article $article)
    {
        $user = $request->user();
        $this->authorizeArticleAccess($user, $article);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'organization_id' => 'nullable|exists:organizations,id',
            'excerpt' => 'nullable|string',
            'content' => 'required|string',
            'is_published' => 'boolean',
            'is_featured' => 'boolean',
            'cover_image' => 'nullable|image|max:2048',
            'categories' => 'nullable|array',
            'categories.*' => 'exists:article_categories,id',
            'tags' => 'nullable|string',
            'gallery' => 'nullable|array',
            'gallery.*' => 'image|max:4096',
        ]);

        if ($request->hasFile('cover_image')) {
            if ($article->cover_image_path) {
                Storage::disk('public')->delete(str_replace('/storage/', '', $article->cover_image_path));
            }
            $path = $request->file('cover_image')->store('articles', 'public');
            $validated['cover_image_path'] = '/storage/'.$path;
        }
        unset($validated['cover_image']);

        $validated['is_published'] = filter_var($request->input('is_published', false), FILTER_VALIDATE_BOOLEAN);
        $validated['is_featured'] = $request->boolean('is_featured', false);

        if ($validated['is_published'] && ! $article->published_at) {
            $validated['published_at'] = now();
        }

        $categoryIds = $request->input('categories', []);
        unset($validated['categories']);

        $tagNames = $request->input('tags', '');
        unset($validated['tags']);

        $galleryFiles = $request->file('gallery', []);
        unset($validated['gallery']);

        $validated['organization_id'] = $user->isSuperadmin()
            ? ($validated['organization_id'] ?? null)
            : $user->current_organization_id;

        if ($user->isBranchAdmin()) {
            $validated['branch_id'] = $user->managedBranchId();
        }

        $article->update($validated);

        if (isset($categoryIds)) {
            $article->categories()->sync($categoryIds);
        }

        if ($tagNames !== null) {
            $tagIds = $this->syncTags($tagNames);
            $article->tags()->sync($tagIds);
        }

        foreach ($galleryFiles as $file) {
            $path = $file->store('articles/gallery', 'public');
            $article->media()->create([
                'path' => '/storage/'.$path,
                'type' => 'image',
            ]);
        }

        return back()->with('success', 'Artikel berjaya dikemaskini.');
    }

    public function destroy(Request $request, Article $article)
    {
        $this->authorizeArticleAccess($request->user(), $article);

        if ($article->cover_image_path) {
            Storage::disk('public')->delete(str_replace('/storage/', '', $article->cover_image_path));
        }

        foreach ($article->media as $media) {
            Storage::disk('public')->delete(str_replace('/storage/', '', $media->path));
        }

        $article->delete();

        return redirect()->route('admin.articles.index')
            ->with('success', 'Artikel berjaya dipadam.');
    }

    public function deleteMedia(Request $request, ArticleMedia $media)
    {
        $media->loadMissing('article');
        abort_if(! $media->article, 404);
        $this->authorizeArticleAccess($request->user(), $media->article);

        Storage::disk('public')->delete(str_replace('/storage/', '', $media->path));
        $media->delete();

        return back()->with('success', 'Gambar berjaya dipadam.');
    }

    /**
     * Organisation admins may only touch articles that belong to their own
     * organisation. Global articles (organization_id null) are Superadmin-only.
     */
    private function authorizeArticleAccess($user, Article $article): void
    {
        if ($user->isSuperadmin()) {
            return;
        }

        if ((int) $article->organization_id !== (int) $user->current_organization_id) {
            abort(403);
        }

        if ($user->isBranchAdmin() && (int) $article->branch_id !== (int) $user->managedBranchId()) {
            abort(403);
        }
    }

    private function manageableOrganizations($user)
    {
        return $user->isSuperadmin()
            ? Organization::orderBy('name')->get()
            : Organization::where('id', $user->current_organization_id)->get();
    }

    private function syncTags($tagNames)
    {
        $names = array_filter(array_map('trim', explode(',', $tagNames)));
        $ids = [];

        foreach ($names as $name) {
            $tag = ArticleTag::firstOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name]
            );
            $ids[] = $tag->id;
        }

        return $ids;
    }
}
