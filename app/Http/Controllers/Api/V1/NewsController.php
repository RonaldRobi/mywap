<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\NewsPost;
use App\Services\NewsService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NewsController extends Controller
{
    public function __construct(private readonly NewsService $news) {}

    public function index(Request $request): JsonResponse
    {
        $paginator = $this->news->list($request, $request->user());

        return ApiResponse::paginated($paginator, [
            'categories' => $this->news->categories(),
            'filters' => [
                'category_id' => $request->integer('category_id') ?: null,
            ],
        ]);
    }

    public function show(Request $request, NewsPost $post): JsonResponse
    {
        $user = $request->user();
        abort_unless($this->news->canViewPost($user, $post), 404);

        $detail = $this->news->showDetail($post, $user);

        return ApiResponse::success($detail);
    }

    public function react(Request $request, NewsPost $post): JsonResponse
    {
        $user = $request->user();
        abort_unless($this->news->canViewPost($user, $post), 404);

        $validated = $request->validate([
            'reaction' => ['required', 'in:like,dislike'],
        ]);

        $state = $this->news->react($user, $post, $validated['reaction']);

        return ApiResponse::success($state, ['message' => 'Reaksi berjaya dikemas kini.']);
    }

    public function storeComment(Request $request, NewsPost $post): JsonResponse
    {
        $user = $request->user();
        abort_unless($this->news->canViewPost($user, $post), 404);

        $validated = $request->validate([
            'content' => ['required', 'string', 'max:1500'],
        ]);

        $comment = $this->news->addComment($user, $post, $validated['content']);

        return ApiResponse::success(['comment' => $comment], ['message' => 'Komen berjaya dihantar.'], 201);
    }
}
