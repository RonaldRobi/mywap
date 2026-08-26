<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\VideoService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VideoController extends Controller
{
    public function __construct(private readonly VideoService $videos) {}

    public function index(Request $request): JsonResponse
    {
        $paginator = $this->videos->list($request, $request->user());

        return ApiResponse::paginated($paginator);
    }
}
