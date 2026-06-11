<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;
use App\Models\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class OrderController extends Controller
{
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
        $order->load('items.product', 'user');

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
        ]);
        DB::beginTransaction();
        try {
            $total = 0;
            $items = [];
            foreach ($request->products as $item) {
                $product = Product::findOrFail($item['id']);
                if ($product->stock < $item['quantity']) {
                    throw new \Exception('Stok tidak mencukupi untuk ' . $product->name);
                }
                $total += $product->price * $item['quantity'];
                $items[] = [
                    'product_id' => $product->id,
                    'quantity' => $item['quantity'],
                    'price' => $product->price,
                ];
                $product->decrement('stock', $item['quantity']);
            }
            $order = Order::create([
                'user_id' => Auth::id(),
                'organisasi_id' => Auth::user()->organisasi_id ?? null,
                'total' => $total,
                'status' => 'pending',
            ]);
            foreach ($items as $item) {
                $item['order_id'] = $order->id;
                OrderItem::create($item);
            }
            DB::commit();
            return redirect()->route('orders.show', $order)->with('success', 'Pesanan berjaya dibuat!');
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
}
