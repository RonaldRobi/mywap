<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\MediaStatement;
use App\Services\MediaStatementService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MediaStatementController extends Controller
{
    public function __construct(private readonly MediaStatementService $statements) {}

    public function index(Request $request): JsonResponse
    {
        return ApiResponse::paginated($this->statements->list($request, $request->user()));
    }

    public function show(Request $request, MediaStatement $mediaStatement): JsonResponse
    {
        abort_unless($this->statements->visibleQuery($request->user())->whereKey($mediaStatement)->exists(), 404);

        return ApiResponse::success($this->statements->detail($mediaStatement));
    }
}
