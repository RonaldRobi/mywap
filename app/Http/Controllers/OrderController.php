<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductVariationOption;
use App\Services\BayarCashService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class OrderController extends Controller
{
    public function __construct(
        protected BayarCashService $bayarCashService,
    ) {}

    public function index()
    {
        $this->authorize('viewAny', Order::class);
        $user = Auth::user();
        $isAdmin = $user->hasAnyRole(['Admin', 'Superadmin']);

        $ordersQuery = $isAdmin
            ? Order::query()
            : $user->orders();

        $orders = $ordersQuery
            ->with('items.product')
            ->latest()
            ->paginate(15);

        return Inertia::render('Ecommerce/Orders/Index', [
            'orders' => $orders,
            'canManageAll' => $isAdmin,
        ]);
    }

    public function show(Order $order)
    {
        $this->authorize('view', $order);
        $order->load('items.product', 'items.variationOption.variation', 'user', 'payments');

        return Inertia::render('Ecommerce/Orders/Show', [
            'order' => $order,
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

        $order->load('items.product', 'items.variationOption.variation', 'user', 'payments');

        return Inertia::render('Ecommerce/Orders/Show', [
            'order' => $order,
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('create', Order::class);
        $request->validate([
            'products' => 'required|array',
            'products.*.id' => 'required|exists:products,id',
            'products.*.quantity' => 'required|integer|min:1',
            'products.*.variation_option_id' => 'nullable|exists:product_variation_options,id',
            'products.*.variation_snapshot' => 'nullable|string',
            'shipping_name' => 'nullable|string|max:255',
            'shipping_address' => 'nullable|string',
            'shipping_postcode' => 'nullable|string|max:10',
            'shipping_phone' => 'nullable|string|max:20',
        ]);

        DB::beginTransaction();
        try {
            $productIds = collect($request->products)->pluck('id');
            $products = Product::whereIn('id', $productIds)->lockForUpdate()->get()->keyBy('id');

            $total = 0;
            $totalPostage = 0;
            $items = [];

            foreach ($request->products as $item) {
                $product = $products->get($item['id']);
                if (! $product) {
                    throw new \Exception('Produk tidak dijumpai.');
                }

                if (! $product->status) {
                    throw new \Exception('Produk "'.$product->name.'" tidak lagi dijual.');
                }

                $quantity = $item['quantity'];
                $unitPrice = (float) $product->price;
                $variationSnapshot = null;
                $variationOptionId = null;

                // Handle variation option
                if (! empty($item['variation_option_id'])) {
                    $option = ProductVariationOption::with('variation')
                        ->where('id', $item['variation_option_id'])
                        ->whereHas('variation', fn ($q) => $q->where('product_id', $product->id))
                        ->lockForUpdate()
                        ->first();

                    if (! $option) {
                        throw new \Exception('Pilihan variasi tidak sah.');
                    }

                    $variationOptionId = $option->id;

                    // Check stock per option if set
                    if ($option->stock !== null && $option->stock < $quantity) {
                        throw new \Exception('Stok tidak mencukupi untuk '.$product->name.' - '.$option->name);
                    }

                    // Apply price adjustment
                    if ($option->price_adjustment) {
                        $unitPrice += (float) $option->price_adjustment;
                    }

                    // Decrement option stock if tracked separately
                    if ($option->stock !== null) {
                        $option->decrement('stock', $quantity);
                    }

                    $variationSnapshot = $item['variation_snapshot'] ?? json_encode([
                        'variation' => $option->variation->name,
                        'option' => $option->name,
                    ]);
                } else {
                    // No variation — check product-level stock
                    if ($product->stock < $quantity) {
                        throw new \Exception('Stok tidak mencukupi untuk '.$product->name);
                    }
                    $product->decrement('stock', $quantity);
                    $variationSnapshot = $item['variation_snapshot'] ?? null;
                }

                $lineTotal = $unitPrice * $quantity;
                $total += $lineTotal;

                $items[] = [
                    'product_id' => $product->id,
                    'product_variation_option_id' => $variationOptionId,
                    'variation_snapshot' => $variationSnapshot,
                    'quantity' => $quantity,
                    'price' => $unitPrice,
                ];

                // Combined postage is the single highest item postage, matching
                // checkout(). Summing here instead would overcharge anyone
                // ordering from the dashboard rather than the mall.
                if ($product->postage_cost && (float) $product->postage_cost > $totalPostage) {
                    $totalPostage = (float) $product->postage_cost;
                }
            }

            $sellingOrgId = $products->pluck('organisasi_id')->filter()->first();

            $order = Order::create([
                'user_id' => Auth::id(),
                'organisasi_id' => $sellingOrgId ?? Auth::user()->current_organization_id,
                'total' => $total,
                'postage_cost' => $totalPostage,
                'status' => 'pending',
                'shipping_name' => $request->shipping_name,
                'shipping_address' => $request->shipping_address,
                'shipping_postcode' => $request->shipping_postcode,
                'shipping_phone' => $request->shipping_phone,
            ]);

            foreach ($items as $item) {
                $item['order_id'] = $order->id;
                OrderItem::create($item);
            }

            DB::commit();

            return redirect()->to($order->publicUrl())->with('success', 'Pesanan berjaya dibuat!');
        } catch (\Exception $e) {
            DB::rollBack();

            return back()->withErrors(['error' => $e->getMessage()]);
        }
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

    public function pay(Order $order): RedirectResponse
    {
        $this->authorize('view', $order);

        if ($order->status !== 'pending') {
            return redirect()->route('orders.show', $order)->with('error', 'Pesanan ini sudah dibayar.');
        }

        $user = Auth::user();
        $orgId = $order->organisasi_id ?? $user->current_organization_id;
        $org = Organization::find($orgId);
        $useBayarCash = $org && $org->hasBayarCashConfig();

        $grandTotal = (float) $order->total + (float) $order->postage_cost;

        $payment = Payment::create([
            'user_id' => $user->id,
            'payable_type' => 'order',
            'payable_id' => $order->id,
            'amount' => $grandTotal,
            'status' => $useBayarCash ? 'pending' : 'successful',
            'reference' => $useBayarCash ? 'ORD-'.strtoupper(Str::random(8)) : 'DUMMY-'.strtoupper(Str::random(8)),
            'description' => "Pesanan #{$order->id}",
            'gateway' => $useBayarCash ? 'bayarcash' : 'dummy',
            'organization_id' => $org?->id,
        ]);

        if ($useBayarCash && $org) {
            $url = $this->bayarCashService->createPaymentIntent(
                $org,
                $payment,
                $user->name,
                $user->email,
            );

            if ($url) {
                return redirect()->away($url);
            }

            $payment->update(['status' => 'failed']);

            return back()->with('error', 'Pembayaran gagal diproses. Sila cuba lagi.');
        }

        $order->update(['status' => 'paid']);

        return redirect()->to($order->publicUrl())->with('success', 'Pesanan berjaya dibayar!');
    }

    /**
     * Mall checkout — guest & member friendly.
     * Accepts a cart products array from the client and creates a single Order.
     * Combined postage uses the HIGHEST item postage, not the sum.
     */
    public function checkout(Request $request): RedirectResponse
    {
        $user = $request->user();
        $isMember = $user && $user->hasRole('Member');

        $rules = [
            'products' => 'required|array|min:1',
            'products.*.id' => 'required|exists:products,id',
            'products.*.quantity' => 'required|integer|min:1',
            'products.*.variation_option_id' => 'nullable|exists:product_variation_options,id',
            'products.*.variation_snapshot' => 'nullable|string',
            'shipping_name' => [$isMember ? 'nullable' : 'required', 'string', 'max:255'],
            'shipping_phone' => [$isMember ? 'nullable' : 'required', 'string', 'max:20'],
            'shipping_address' => 'nullable|string',
            'shipping_postcode' => 'nullable|string|max:10',
        ];

        $data = $request->validate($rules);

        $shippingName = ($data['shipping_name'] ?? null) ?: ($user?->name ?? null);
        $shippingPhone = ($data['shipping_phone'] ?? null) ?: ($user?->phone ?? null);

        DB::beginTransaction();
        try {
            $productIds = collect($data['products'])->pluck('id');
            $products = Product::whereIn('id', $productIds)->lockForUpdate()->get()->keyBy('id');

            $total = 0;
            $combinedPostage = 0;
            $items = [];

            foreach ($data['products'] as $item) {
                $product = $products->get($item['id']);
                if (! $product) {
                    throw new \Exception('Produk tidak dijumpai.');
                }

                // Drafts are not purchasable. Without this a stale cart (or a
                // crafted request) could buy a product the admin unpublished.
                if (! $product->status) {
                    throw new \Exception('Produk "'.$product->name.'" tidak lagi dijual.');
                }

                $quantity = $item['quantity'];
                $unitPrice = (float) $product->price;
                $variationSnapshot = null;
                $variationOptionId = null;

                if (! empty($item['variation_option_id'])) {
                    $option = ProductVariationOption::with('variation')
                        ->where('id', $item['variation_option_id'])
                        ->whereHas('variation', fn ($q) => $q->where('product_id', $product->id))
                        ->lockForUpdate()
                        ->first();

                    if (! $option) {
                        throw new \Exception('Pilihan variasi tidak sah.');
                    }
                    $variationOptionId = $option->id;

                    if ($option->stock !== null && $option->stock < $quantity) {
                        throw new \Exception('Stok tidak mencukupi untuk '.$product->name.' - '.$option->name);
                    }
                    if ($option->price_adjustment) {
                        $unitPrice += (float) $option->price_adjustment;
                    }
                    if ($option->stock !== null) {
                        $option->decrement('stock', $quantity);
                    }
                    $variationSnapshot = $item['variation_snapshot'] ?? json_encode([
                        'variation' => $option->variation->name,
                        'option' => $option->name,
                    ]);
                } else {
                    if ($product->stock < $quantity) {
                        throw new \Exception('Stok tidak mencukupi untuk '.$product->name);
                    }
                    $product->decrement('stock', $quantity);
                    $variationSnapshot = $item['variation_snapshot'] ?? null;
                }

                if ($isMember && $product->member_price !== null) {
                    $unitPrice = (float) $product->member_price;
                }

                $lineTotal = $unitPrice * $quantity;
                $total += $lineTotal;

                $items[] = [
                    'product_id' => $product->id,
                    'product_variation_option_id' => $variationOptionId,
                    'variation_snapshot' => $variationSnapshot,
                    'quantity' => $quantity,
                    'price' => $unitPrice,
                ];

                if ($product->postage_cost && (float) $product->postage_cost > $combinedPostage) {
                    $combinedPostage = (float) $product->postage_cost;
                }
            }

            // Attribute the order to the organisation that actually sells the
            // goods. Falling back to the buyer's own org left every guest
            // order with a null organisasi_id, which made it invisible to the
            // selling org's Admins — only a Superadmin could action them.
            $sellingOrgId = $products
                ->pluck('organisasi_id')
                ->filter()
                ->first();

            $order = Order::create([
                'user_id' => $user?->id,
                'organisasi_id' => $sellingOrgId ?? $user?->current_organization_id,
                'total' => $total,
                'postage_cost' => $combinedPostage,
                'status' => 'pending',
                'shipping_name' => $shippingName,
                'shipping_address' => $data['shipping_address'] ?? null,
                'shipping_postcode' => $data['shipping_postcode'] ?? null,
                'shipping_phone' => $shippingPhone,
            ]);

            foreach ($items as $itemData) {
                $itemData['order_id'] = $order->id;
                OrderItem::create($itemData);
            }

            DB::commit();

            return redirect()->to($order->publicUrl())->with('success', 'Pesanan berjaya dibuat!');
        } catch (\Exception $e) {
            DB::rollBack();

            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }
}
