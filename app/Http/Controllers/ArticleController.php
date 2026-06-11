<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\Organization;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;

class ArticleController extends Controller
{
    public function manage()
    {
        $articles = Article::with(['author', 'organization'])
            ->latest('published_at')
            ->paginate(10);

        return Inertia::render('Admin/ArticleManage', [
            'articles' => $articles,
            'organizations' => Organization::all(),
        ]);
    }

    public function index()
    {
        $articles = Article::with(['author', 'organization'])
            ->where('is_published', true)
            ->where(function ($query) {
                $query->whereNull('published_at')
                      ->orWhere('published_at', '<=', now());
            })
            ->latest('published_at')
            ->get()
            ->map(fn($article) => [
                'id' => $article->id,
                'title' => $article->title,
                'slug' => $article->slug,
                'cover_image' => $article->cover_image_path,
                'excerpt' => Str::limit(strip_tags($article->content), 100),
                'author_name' => $article->author?->name ?? 'Admin',
                'published_date' => $article->published_at ? $article->published_at->format('d M Y') : $article->created_at->format('d M Y'),
                'public_url' => route('articles.show', $article->slug),
            ]);

        return Inertia::render('Article/Index', [
            'articles' => $articles
        ]);
    }

    public function show(\Illuminate\Http\Request $request, Article $article)
    {
        abort_if(!$article->is_published, 404);
        abort_if($article->published_at && $article->published_at->isFuture(), 404);

        $article->load(['author:id,name', 'organization:id,name,slug']);

        $user = $request->user();
        $sessionId = $request->session()->getId();

        $likes = $article->reactions()->where('reaction', 'like')->count();
        $dislikes = $article->reactions()->where('reaction', 'dislike')->count();
        $myReaction = $article->reactions()
            ->where(function($q) use ($user, $sessionId) {
                if ($user) $q->where('user_id', $user->id);
                else $q->where('session_id', $sessionId);
            })->value('reaction');

        $comments = $article->comments()
            ->where('is_hidden', false)
            ->with('user:id,name')
            ->latest()
            ->take(100)
            ->get()
            ->map(fn ($comment) => [
                'id' => $comment->id,
                'content' => $comment->content,
                'user_name' => $comment->user?->name ?? $comment->anonymous_name ?? 'Anonim',
                'created_at' => $comment->created_at?->diffForHumans(),
            ]);

        return Inertia::render('Article/Show', [
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
            ],
            'comments' => $comments,
        ]);
    }

    public function react(\Illuminate\Http\Request $request, Article $article)
    {
        abort_if(!$article->is_published, 404);

        $data = $request->validate([
            'reaction' => ['required', 'in:like,dislike'],
        ]);

        $user = $request->user();
        $sessionId = $request->session()->getId();

        $existing = \App\Models\ArticleReaction::query()
            ->where('article_id', $article->id)
            ->where(function($q) use ($user, $sessionId) {
                if ($user) $q->where('user_id', $user->id);
                else $q->where('session_id', $sessionId);
            })
            ->first();

        if ($existing && $existing->reaction === $data['reaction']) {
            $existing->delete();
        } elseif ($existing) {
            $existing->update(['reaction' => $data['reaction']]);
        } else {
            \App\Models\ArticleReaction::create([
                'article_id' => $article->id,
                'user_id' => $user?->id,
                'session_id' => $user ? null : $sessionId,
                'reaction' => $data['reaction'],
            ]);
        }

        return back()->with('success', 'Reaksi berjaya dikemas kini.');
    }

    public function storeComment(\Illuminate\Http\Request $request, Article $article)
    {
        abort_if(!$article->is_published, 404);

        $data = $request->validate([
            'content' => ['required', 'string', 'max:1500'],
            'anonymous_name' => ['nullable', 'string', 'max:50'],
        ]);

        \App\Models\ArticleComment::create([
            'article_id' => $article->id,
            'user_id' => $request->user()?->id,
            'anonymous_name' => $request->user() ? null : ($data['anonymous_name'] ?? 'Anonim'),
            'content' => trim($data['content']),
            'is_hidden' => false,
        ]);

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
            'cover_image' => 'nullable|image|max:2048',
        ]);

        if ($request->hasFile('cover_image')) {
            $path = $request->file('cover_image')->store('articles', 'public');
            $validated['cover_image_path'] = '/storage/' . $path;
        }
        unset($validated['cover_image']);

        $validated['author_id'] = auth()->id();
        $validated['slug'] = Str::slug($validated['title']) . '-' . uniqid();
        $validated['is_published'] = filter_var($request->input('is_published', false), FILTER_VALIDATE_BOOLEAN);
        
        if ($validated['is_published']) {
            $validated['published_at'] = now();
        }

        Article::create($validated);

        return back()->with('success', 'Artikel berjaya dicipta.');
    }

    public function update(Request $request, Article $article)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'organization_id' => 'nullable|exists:organizations,id',
            'excerpt' => 'nullable|string',
            'content' => 'required|string',
            'is_published' => 'boolean',
            'cover_image' => 'nullable|image|max:2048',
        ]);

        if ($request->hasFile('cover_image')) {
            if ($article->cover_image_path) {
                Storage::disk('public')->delete(str_replace('/storage/', '', $article->cover_image_path));
            }
            $path = $request->file('cover_image')->store('articles', 'public');
            $validated['cover_image_path'] = '/storage/' . $path;
        }
        unset($validated['cover_image']);

        $validated['is_published'] = filter_var($request->input('is_published', false), FILTER_VALIDATE_BOOLEAN);

        if ($validated['is_published'] && !$article->published_at) {
            $validated['published_at'] = now();
        }

        $article->update($validated);

        return back()->with('success', 'Artikel berjaya dikemaskini.');
    }

    public function destroy(Article $article)
    {
        if ($article->cover_image_path) {
            Storage::disk('public')->delete(str_replace('/storage/', '', $article->cover_image_path));
        }
        $article->delete();

        return back()->with('success', 'Artikel berjaya dipadam.');
    }
}
