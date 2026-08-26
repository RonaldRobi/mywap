<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\MemberDashboardService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MemberDashboardController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $data = app(MemberDashboardService::class)->data($request->user());

        return ApiResponse::success($data, ['cached' => false]);
    }
}
