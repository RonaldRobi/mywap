<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\OrderService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function __construct(private readonly OrderService $orders) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Order::class);

        return ApiResponse::paginated($this->orders->list($request, $request->user()));
    }

    public function show(Request $request, Order $order): JsonResponse
    {
        $this->authorize('view', $order);

        return ApiResponse::success($this->orders->showDetail($order));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate(OrderService::checkoutRules($request->user()));

        try {
            $result = $this->orders->checkout($validated, $request->user());
        } catch (\Exception $e) {
            return ApiResponse::error($e->getMessage(), [], 422);
        }

        return ApiResponse::success([
            'order' => $this->orders->serialize($result['order']),
            'payment_url' => $result['payment_url'] ?? null,
        ], ['message' => 'Pesanan berjaya dibuat!']);
    }

    public function pay(Request $request, Order $order): JsonResponse
    {
        $this->authorize('view', $order);

        $result = $this->orders->pay($order, $request->user());

        if (in_array($result['status'], ['error', 'already_paid'])) {
            return ApiResponse::error($result['message'], [], 422);
        }

        return ApiResponse::success([
            'status' => $result['status'],
            'payment_url' => $result['payment_url'] ?? null,
        ]);
    }

    /**
     * Tandai pesanan sendiri sebagai diterima ('shipped' → 'completed').
     * Admin tidak boleh menanda received bagi pihak pembeli — hanya pemilik
     * order. (Admin guna dashboard web untuk kemaskini status/hantar.)
     */
    public function receive(Request $request, Order $order): JsonResponse
    {
        $this->authorize('view', $order);

        if ($order->user_id !== $request->user()->id) {
            return ApiResponse::error('Tiada kebenaran.', [], 403);
        }

        $result = $this->orders->markReceived($order);

        if ($result['status'] === 'error') {
            return ApiResponse::error($result['message'], [], 422);
        }

        return ApiResponse::success($this->orders->serialize($result['order']));
    }
}
