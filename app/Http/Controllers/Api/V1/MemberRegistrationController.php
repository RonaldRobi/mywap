<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\RegistrationService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MemberRegistrationController extends Controller
{
    public function __construct(private readonly RegistrationService $registrations)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $paginator = $this->registrations->memberRegistrations($request->user());

        return ApiResponse::paginated($paginator);
    }
}
