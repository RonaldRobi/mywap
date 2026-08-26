<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\NotificationService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function __construct(private readonly NotificationService $notifications) {}

    public function index(Request $request): JsonResponse
    {
        return ApiResponse::success([
            'notifications' => $this->notifications->notifications($request->user()),
        ]);
    }

    public function readAll(Request $request): JsonResponse
    {
        $this->notifications->markAllRead($request->user());

        return ApiResponse::success(['success' => true]);
    }
}
