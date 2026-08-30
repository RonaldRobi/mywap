<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductVariationOption;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * OrderService
 *
 * Logik tunggal untuk domain pesanan (order, checkout, payment) — dikongsi oleh
 * WebController (Inertia) dan ApiController (JSON) supaya web & Flutter tidak
 * drift. Rujuk docs/FLUTTER_PLAN.md §4.
 */
class OrderService
{
    public function __construct(
        protected PaymentGatewayManager $gateways,
    ) {}

    /**
     * Aturan validasi untuk order store (dashboard, ahli berdaftar).
     */
    public static function storeRules(): array
    {
        return [
            'products' => 'required|array',
            'products.*.id' => 'required|exists:products,id',
            'products.*.quantity' => 'required|integer|min:1',
            'products.*.variation_option_id' => 'nullable|exists:product_variation_options,id',
            'products.*.variation_snapshot' => 'nullable|string',
            'shipping_name' => 'nullable|string|max:255',
            'shipping_address' => 'nullable|string',
            'shipping_postcode' => 'nullable|string|max:10',
            'shipping_phone' => 'nullable|string|max:20',
        ];
    }

    /**
     * Aturan validasi untuk checkout mall — tetamu wajib isi nama & telefon,
     * ahli berdaftar boleh kosong (guna profil).
     */
    public static function checkoutRules(?User $user): array
    {
        $isMember = $user && $user->hasRole('Member');

        return [
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
    }

    /**
     * Serialize satu Order kepada bentuk konsisten untuk web & API.
     */
    public function serialize(Order $order): array
    {
        return [
            'id' => $order->id,
            'user_id' => $order->user_id,
            'organisasi_id' => $order->organisasi_id,
            'total' => number_format((float) $order->total, 2, '.', ''),
            'postage_cost' => number_format((float) $order->postage_cost, 2, '.', ''),
            'status' => $order->status,
            'tracking_no' => $order->tracking_no,
            'shipping_name' => $order->shipping_name,
            'shipping_address' => $order->shipping_address,
            'shipping_postcode' => $order->shipping_postcode,
            'shipping_phone' => $order->shipping_phone,
            'courier' => $order->courier,
            'created_at' => $order->created_at,
            'updated_at' => $order->updated_at,
            'items' => $order->relationLoaded('items')
                ? $order->items->map(fn ($item) => [
                    'id' => $item->id,
                    'product_id' => $item->product_id,
                    'product' => $item->product ? [
                        'id' => $item->product->id,
                        'name' => $item->product->name,
                        'price' => $item->product->price,
                        'image' => $item->product->image,
                    ] : null,
                    'product_variation_option_id' => $item->product_variation_option_id,
                    'variation_snapshot' => $item->variation_snapshot,
                    'quantity' => $item->quantity,
                    'price' => $item->price,
                ])->values()
                : [],
            'payments' => $order->relationLoaded('payments')
                ? $order->payments->map(fn ($p) => [
                    'id' => $p->id,
                    'amount' => $p->amount,
                    'status' => $p->status,
                    'reference' => $p->reference,
                    'gateway' => $p->gateway,
                    'description' => $p->description,
                    'created_at' => $p->created_at,
                ])->values()
                : [],
            'user' => $order->relationLoaded('user') && $order->user
                ? [
                    'id' => $order->user->id,
                    'name' => $order->user->name,
                    'email' => $order->user->email,
                    'phone' => $order->user->phone,
                ]
                : null,
        ];
    }

    /**
     * Senarai pesanan mengikut skop: admin/superadmin semua, ahli pesanan sendiri.
     */
    public function list(Request $request, User $user): LengthAwarePaginator
    {
        $isAdmin = $user->hasAnyRole(['Admin', 'Superadmin']);

        $query = $isAdmin
            ? Order::query()
            : $user->orders();

        return $query
            ->with('items.product')
            ->latest()
            ->paginate(self::perPage($request))
            ->withQueryString()
            ->through(fn (Order $order) => $this->serialize($order));
    }

    /**
     * Payload penuh untuk halaman/endpoint show pesanan.
     */
    public function showDetail(Order $order): array
    {
        $order->loadMissing('items.product', 'items.variationOption.variation', 'user', 'payments');

        return $this->serialize($order);
    }

    /**
     * Teras bersama store() (dashboard) dan checkout() (mall).
     *
     * Transaction + `lockForUpdate` stok dipelihara supaya dua checkout serentak
     * tidak boleh over-sell. `$applyMemberPricing` = ahli berdaftar layak harga
     * ahli (web checkout / API checkout); store dashboard sentiasa harga biasa.
     *
     * @throws \Exception bila mana-mana produk tidak sah / stok tidak cukup.
     */
    public function createOrder(array $data, ?User $user = null, bool $applyMemberPricing = false): Order
    {
        $shippingName = $data['shipping_name'] ?? null;
        $shippingPhone = $data['shipping_phone'] ?? null;

        if ($applyMemberPricing) {
            $shippingName = $shippingName ?: ($user?->name ?? null);
            $shippingPhone = $shippingPhone ?: ($user?->phone ?? null);
        }

        return DB::transaction(function () use ($data, $user, $applyMemberPricing, $shippingName, $shippingPhone) {
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

                if (! $product->status) {
                    throw new \Exception('Produk "'.$product->name.'" tidak lagi dijual.');
                }

                if (! $product->organisasi_id) {
                    throw new \Exception('Produk "'.$product->name.'" belum ditetapkan kepada mana-mana organisasi penjual.');
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

                if ($applyMemberPricing && $product->member_price !== null) {
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

                // Pos gabungan = pos tertinggi item, bukan jumlah — sama dengan
                // checkout() supaya pengguna dashboard tidak terlebih caj.
                if ($product->postage_cost && (float) $product->postage_cost > $combinedPostage) {
                    $combinedPostage = (float) $product->postage_cost;
                }
            }

            $sellingOrgId = $products->pluck('organisasi_id')->filter()->first();

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

            $now = now();

            foreach ($items as &$itemData) {
                $itemData['order_id'] = $order->id;
                $itemData['created_at'] = $now;
                $itemData['updated_at'] = $now;
            }
            unset($itemData);

            if ($items !== []) {
                OrderItem::insert($items);
            }

            return $order;
        });
    }

    /**
     * Checkout API (ahli berdaftar) — order + harga ahli, dan jika organisasi
     * penjual ada gateway aktif, cipta Payment + URL redirect untuk dibayar.
     *
     * @return array{status: string, order: Order, payment_url?: string}
     */
    public function checkout(array $data, User $user): array
    {
        $order = $this->createOrder($data, $user, true);

        $org = Organization::find($order->organisasi_id);
        if ($org && $this->gateways->isLive($org)) {
            $result = $this->initiatePayment($order, $user);

            $order->loadMissing('items.product', 'items.variationOption.variation');

            if ($result['status'] === 'redirect') {
                return ['status' => 'redirect', 'payment_url' => $result['payment_url'], 'order' => $order];
            }
        }

        $order->loadMissing('items.product', 'items.variationOption.variation');

        return ['status' => 'success', 'order' => $order];
    }

    /**
     * Proses pembayaran pesanan. Result:
     *   ['status' => 'redirect', 'payment_url' => ...]  → gateway aktif
     *   ['status' => 'success']                          → tiada gateway, tandai paid
     *   ['status' => 'error', 'message' => ...]          → gagal diproses
     *   ['status' => 'already_paid', 'message' => ...]   → order bukan pending
     */
    public function pay(Order $order, User $user): array
    {
        if ($order->status !== 'pending') {
            return ['status' => 'already_paid', 'message' => 'Pesanan ini sudah dibayar.'];
        }

        $result = $this->initiatePayment($order, $user);

        if ($result['status'] === 'no_gateway') {
            $order->update(['status' => 'paid']);

            return ['status' => 'success'];
        }

        return $result;
    }

    /**
     * Cipta Payment row dan, jika gateway aktif, minta URL redirect daripada
     * PaymentGatewayManager. Tanpa gateway → status 'no_gateway' (payment dummy
     * berjaya direkod, pemanggil menanda order paid).
     */
    protected function initiatePayment(Order $order, User $user): array
    {
        $orgId = $order->organisasi_id ?? $user->current_organization_id;
        $org = Organization::find($orgId);
        $useGateway = $this->gateways->isLive($org);

        $grandTotal = (float) $order->total + (float) $order->postage_cost;

        $payment = Payment::create([
            'user_id' => $user->id,
            'payable_type' => 'order',
            'payable_id' => $order->id,
            'amount' => $grandTotal,
            'status' => $useGateway ? 'pending' : 'successful',
            'reference' => $useGateway ? 'ORD-'.strtoupper(Str::random(8)) : 'DUMMY-'.strtoupper(Str::random(8)),
            'description' => "Pesanan #{$order->id}",
            'gateway' => $this->gateways->gatewayFor($org),
            'organization_id' => $org?->id,
        ]);

        if ($useGateway && $org) {
            $url = $this->gateways->createPaymentRedirect(
                $org,
                $payment,
                $user->name,
                $user->email,
                $user->phone ?? null,
                "Pesanan #{$order->id}",
            );

            if ($url) {
                return ['status' => 'redirect', 'payment_url' => $url];
            }

            $payment->update(['status' => 'failed']);

            return ['status' => 'error', 'message' => 'Pembayaran gagal diproses. Sila cuba lagi.'];
        }

        return ['status' => 'no_gateway'];
    }

    /**
     * Pastikan per_page hanya nilai yang dibenarkan (25/50/100 default 15).
     */
    public static function perPage(Request $request, int $default = 15): int
    {
        $perPage = (int) $request->input('per_page', $default);

        return in_array($perPage, [15, 25, 50, 100]) ? $perPage : $default;
    }
}
