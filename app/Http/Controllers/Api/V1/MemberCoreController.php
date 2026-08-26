<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Services\MemberCoreService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MemberCoreController extends Controller
{
    public function __construct(private readonly MemberCoreService $core) {}

    public function card(Request $request): JsonResponse
    {
        return ApiResponse::success($this->core->card($request->user(), true));
    }

    public function feeStatus(Request $request): JsonResponse
    {
        return ApiResponse::success($this->core->feeStatus($request->user()));
    }

    public function announcements(Request $request): JsonResponse
    {
        return ApiResponse::success($this->core->announcements($request->user())->all());
    }

    public function react(Request $request, Announcement $announcement): JsonResponse
    {
        $reaction = $this->core->toggleReaction($request->user(), $announcement);

        return ApiResponse::success(['reaction' => $reaction]);
    }

    public function markRead(Request $request, Announcement $announcement): JsonResponse
    {
        $this->core->markRead($request->user(), $announcement);

        return ApiResponse::success([
            'announcement_id' => $announcement->id,
            'is_read' => true,
        ]);
    }

    public function library(Request $request): JsonResponse
    {
        return ApiResponse::success($this->core->library());
    }
}
