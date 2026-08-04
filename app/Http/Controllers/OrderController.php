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
     * Public order view for guest orders — no auth required.
     */
    public function showPublic(Order $order)
    {
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

                // Use product's postage cost
                if ($product->postage_cost) {
                    $totalPostage += (float) $product->postage_cost;
                }
            }

            $order = Order::create([
                'user_id' => Auth::id(),
                'organisasi_id' => Auth::user()->current_organization_id ?? null,
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

            return redirect()->route('mall.order.show', $order)->with('success', 'Pesanan berjaya dibuat!');
        } catch (\Exception $e) {
            DB::rollBack();

            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    public function updateStatus(Request $request, Order $order)
    {
        $this->authorize('update', $order);
        $request->validate([
            'status' => 'required|string',
            'tracking_no' => 'nullable|string',
        ]);
        $order->update($request->only('status', 'tracking_no'));

        return redirect()->route('orders.show', $order)->with('success', 'Status pesanan dikemaskini!');
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

        return redirect()->route('mall.order.show', $order)->with('success', 'Pesanan berjaya dibayar!');
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

            $order = Order::create([
                'user_id' => $user?->id,
                'organisasi_id' => $user?->current_organization_id ?? null,
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

            return redirect()->route('mall.order.show', $order)->with('success', 'Pesanan berjaya dibuat!');
        } catch (\Exception $e) {
            DB::rollBack();

            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }
}
