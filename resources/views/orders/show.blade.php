@extends('layouts.ecommerce')
@include('nav-ecommerce')
@section('content')
<div class="container mx-auto py-6">
    <h1 class="text-2xl font-bold mb-4">Maklumat Pesanan</h1>
    @if(session('success'))
        <div class="bg-green-100 text-green-800 p-2 rounded mb-4">{{ session('success') }}</div>
    @endif
    <div class="bg-white shadow rounded p-4 mb-4">
        <div class="mb-2">Tarikh: {{ $order->created_at->format('d/m/Y H:i') }}</div>
        <div class="mb-2">Jumlah: <span class="font-semibold">RM{{ number_format($order->total, 2) }}</span></div>
        <div class="mb-2">Status: <span class="font-semibold">{{ ucfirst($order->status) }}</span></div>
        <div class="mb-2">No. Tracking: <span class="font-mono">{{ $order->tracking_no ?? '-' }}</span></div>
    </div>
    <div class="bg-white shadow rounded p-4 mb-4">
        <h2 class="text-lg font-semibold mb-2">Senarai Item</h2>
        <table class="min-w-full">
            <thead>
                <tr>
                    <th class="py-2">Produk</th>
                    <th class="py-2">Kuantiti</th>
                    <th class="py-2">Harga (RM)</th>
                    <th class="py-2">Jumlah (RM)</th>
                </tr>
            </thead>
            <tbody>
                @foreach($order->items as $item)
                <tr>
                    <td class="py-2">{{ $item->product->name ?? '-' }}</td>
                    <td class="py-2">{{ $item->quantity }}</td>
                    <td class="py-2">{{ number_format($item->price, 2) }}</td>
                    <td class="py-2">{{ number_format($item->price * $item->quantity, 2) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @can('update', $order)
    <div class="bg-white shadow rounded p-4 mb-4">
        <h2 class="text-lg font-semibold mb-2">Kemaskini Status Pesanan</h2>
        <form action="{{ route('orders.updateStatus', $order) }}" method="POST" class="flex flex-col md:flex-row gap-2 items-center">
            @csrf
            <select name="status" class="border rounded px-3 py-2" required>
                <option value="pending" @if($order->status=='pending') selected @endif>Pending</option>
                <option value="paid" @if($order->status=='paid') selected @endif>Paid</option>
                <option value="shipped" @if($order->status=='shipped') selected @endif>Shipped</option>
                <option value="completed" @if($order->status=='completed') selected @endif>Completed</option>
                <option value="cancelled" @if($order->status=='cancelled') selected @endif>Cancelled</option>
            </select>
            <input type="text" name="tracking_no" class="border rounded px-3 py-2" placeholder="No. Tracking" value="{{ $order->tracking_no }}">
            <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">Kemaskini</button>
        </form>
    </div>
    @endcan
    <a href="{{ route('orders.index') }}" class="text-gray-600">Kembali ke Senarai Pesanan</a>
</div>
@endsection
