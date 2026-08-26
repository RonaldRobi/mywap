<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\DirectoryService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DirectoryController extends Controller
{
    public function __construct(private readonly DirectoryService $directory) {}

    public function index(Request $request): JsonResponse
    {
        $result = $this->directory->directory($request);

        return ApiResponse::success([
            'users' => $result['users']->items(),
            'industries' => $result['industries']->all(),
            'filters' => $result['filters'],
        ]);
    }

    public function card(string $memberNo): JsonResponse
    {
        return ApiResponse::success([
            'card' => $this->directory->publicCard($memberNo),
        ]);
    }
}
