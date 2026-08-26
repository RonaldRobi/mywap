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
        return ApiResponse::paginated($this->products->list($request, $request->user()));
    }

    public function show(Request $request, Product $product): JsonResponse
    {
        $isAdmin = $request->user() && $request->user()->hasRole(['Superadmin', 'Admin']);
        abort_if(! $product->status && ! $isAdmin, 404);

        return ApiResponse::success($this->products->showDetail($product, $request->user()));
    }

    public function categories(): JsonResponse
    {
        return ApiResponse::paginated($this->products->categories());
    }
}
