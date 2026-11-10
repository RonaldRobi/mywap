<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\ProductService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function __construct(private readonly ProductService $products) {}

    public function index(Request $request): JsonResponse
    {
        return ApiResponse::paginated($this->products->list($request, auth('sanctum')->user()));
    }

    public function show(Request $request, Product $product): JsonResponse
    {
        $user = auth('sanctum')->user();
        $isAdmin = $user && $user->hasRole(['Superadmin', 'Admin', 'org-admin']);
        abort_if(! $product->status && ! $isAdmin, 404);

        return ApiResponse::success($this->products->showDetail($product, $user));
    }

    public function categories(): JsonResponse
    {
        return ApiResponse::paginated($this->products->categories());
    }
}
