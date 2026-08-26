<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Infaq;
use App\Services\InfaqService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InfaqController extends Controller
{
    public function __construct(private readonly InfaqService $infaqs) {}

    public function index(): JsonResponse
    {
        return ApiResponse::success($this->infaqs->index());
    }

    public function show(Infaq $infaq): JsonResponse
    {
        abort_unless((bool) $infaq->is_active, 404);

        return ApiResponse::success($this->infaqs->showDetail($infaq));
    }

    public function donate(Request $request, Infaq $infaq): JsonResponse
    {
        $result = $this->infaqs->donate($request, $infaq, $request->user());

        if ($result['status'] === 'error') {
            return ApiResponse::error($result['message'], [], 422);
        }

        return ApiResponse::success($result);
    }
}
