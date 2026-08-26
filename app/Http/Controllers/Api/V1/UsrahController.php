<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\UsrahService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UsrahController extends Controller
{
    public function __construct(private readonly UsrahService $usrah) {}

    public function index(Request $request): JsonResponse
    {
        $data = $this->usrah->myGroup($request->user());

        return ApiResponse::success($data);
    }
}
