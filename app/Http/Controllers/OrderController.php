<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariationOption;
use App\Services\OrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class OrderController extends Controller
{
    public function __construct(
        protected OrderService $orders,
    ) {}

    public function index()
    {
        $this->authorize('viewAny', Order::class);
        $user = Auth::user();
        $isAdmin = $user->hasAnyRole(['Admin', 'Superadmin']);

        return Inertia::render('Ecommerce/Orders/Index', [
            'orders' => $this->orders->list(request(), $user),
            'canManageAll' => $isAdmin,
        ]);
    }

    public function show(Order $order)
    {
        $this->authorize('view', $order);

        return Inertia::render('Ecommerce/Orders/Show', [
            'order' => $this->orders->showDetail($order),
        ]);
    }

    /**
     * Public order receipt for guest checkouts.
     *
     * Order IDs are sequential, so this endpoint must never serve an arbitrary
     * order to an arbitrary caller. Access requires either a valid signature
     * (the link handed to the guest after checkout) or an authenticated user
     * who owns the order / administers it.
     */
    public function showPublic(Request $request, Order $order)
    {
        $user = $request->user();
        $allowed = $request->hasValidSignature() || ($user && $user->can('view', $order));

        abort_unless($allowed, 403);

        return Inertia::render('Ecommerce/Orders/Show', [
            'order' => $this->orders->showDetail($order),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('create', Order::class);
        $validated = $request->validate(OrderService::storeRules());

        try {
            $order = $this->orders->createOrder($validated, $request->user(), false);
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }

        return redirect()->to($order->publicUrl())->with('success', 'Pesanan berjaya dibuat!');
    }

    public function updateStatus(Request $request, Order $order)
    {
        $this->authorize('update', $order);

        $request->validate([
            'status' => ['required', 'string', Rule::in(Order::STATUSES)],
            'tracking_no' => 'nullable|string|max:100',
        ]);

        $wasCancelled = $order->isCancelled();

        DB::transaction(function () use ($request, $order, $wasCancelled) {
            $order->update($request->only('status', 'tracking_no'));

            // Stock is decremented at checkout, so cancelling has to give it
            // back. Without this every cancelled order permanently eats
            // inventory and the catalogue drifts out of sync with reality.
            if (! $wasCancelled && $order->isCancelled()) {
                $this->restoreStock($order);
            }
        });

        return redirect()->route('orders.show', $order)->with('success', 'Status pesanan dikemaskini!');
    }

    /**
     * Return the quantities reserved by an order back to the catalogue.
     */
    private function restoreStock(Order $order): void
    {
        $order->loadMissing('items');

        foreach ($order->items as $item) {
            if ($item->product_variation_option_id) {
                $option = ProductVariationOption::lockForUpdate()->find($item->product_variation_option_id);

                // A null option stock means that option is not tracked
                // separately, so the product-level counter owns it instead.
                if ($option && $option->stock !== null) {
                    $option->increment('stock', $item->quantity);

                    continue;
                }
            }

            if ($product = Product::lockForUpdate()->find($item->product_id)) {
                $product->increment('stock', $item->quantity);
            }
        }
    }

    public function pay(Order $order): Response
    {
        $this->authorize('view', $order);

        $result = $this->orders->pay($order, Auth::user());

        if ($result['status'] === 'already_paid') {
            return redirect()->route('orders.show', $order)->with('error', $result['message']);
        }

        if ($result['status'] === 'redirect') {
            return Inertia::location($result['payment_url']);
        }

        if ($result['status'] === 'success') {
            return redirect()->to($order->publicUrl())->with('success', 'Pesanan berjaya dibayar!');
        }

        return back()->with('error', $result['message']);
    }

    /**
     * Mall checkout — guest & member friendly.
     * Accepts a cart products array from the client and creates a single Order.
     * Combined postage uses the HIGHEST item postage, not the sum.
     */
    public function checkout(Request $request): RedirectResponse
    {
        $validated = $request->validate(OrderService::checkoutRules($request->user()));

        try {
            $order = $this->orders->createOrder($validated, $request->user(), true);
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }

        return redirect()->to($order->publicUrl())->with('success', 'Pesanan berjaya dibuat!');
    }
}
