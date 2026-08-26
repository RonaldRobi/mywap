<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Services\ArticleService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ArticleController extends Controller
{
    public function __construct(private readonly ArticleService $articles) {}

    public function index(Request $request): JsonResponse
    {
        $paginator = $this->articles->list($request);

        return ApiResponse::paginated($paginator, [
            'featured' => $this->articles->featured(),
            'categories' => $this->articles->categories(),
        ]);
    }

    public function show(Request $request, Article $article): JsonResponse
    {
        abort_if(! $article->is_published, 404);
        abort_if($article->published_at && $article->published_at->isFuture(), 404);

        $detail = $this->articles->showDetail($article, $request->user());

        return ApiResponse::success($detail);
    }

    public function react(Request $request, Article $article): JsonResponse
    {
        abort_if(! $article->is_published, 404);

        $validated = $request->validate([
            'reaction' => ['required', 'in:like,dislike'],
        ]);

        $state = $this->articles->react($article, $validated['reaction'], $request->user());

        return ApiResponse::success($state, ['message' => 'Reaksi berjaya dikemas kini.']);
    }

    public function storeComment(Request $request, Article $article): JsonResponse
    {
        abort_if(! $article->is_published, 404);

        $validated = $request->validate([
            'content' => ['required', 'string', 'max:1500'],
            'anonymous_name' => ['nullable', 'string', 'max:50'],
        ]);

        $comment = $this->articles->addComment(
            $article,
            $validated['content'],
            $request->user(),
            $validated['anonymous_name'] ?? null
        );

        return ApiResponse::success(['comment' => $comment], ['message' => 'Komen berjaya dihantar.'], 201);
    }
}
