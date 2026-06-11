@extends('layouts.ecommerce')
@section('content')
<div class="container mx-auto py-6">
    <h1 class="text-2xl font-bold mb-4">Senarai Pesanan</h1>
    @if(session('success'))
        <div class="bg-green-100 text-green-800 p-2 rounded mb-4">{{ session('success') }}</div>
    @endif
    <div class="bg-white shadow rounded p-4 overflow-x-auto">
        <table class="min-w-full">
            <thead>
                <tr>
                    <th class="py-2">#</th>
                    <th class="py-2">Tarikh</th>
                    <th class="py-2">Jumlah (RM)</th>
                    <th class="py-2">Status</th>
                    <th class="py-2">Tindakan</th>
                </tr>
            </thead>
            <tbody>
                @foreach($orders as $order)
                <tr>
                    <td class="py-2">{{ $loop->iteration }}</td>
                    <td class="py-2">{{ $order->created_at->format('d/m/Y H:i') }}</td>
                    <td class="py-2">{{ number_format($order->total, 2) }}</td>
                    <td class="py-2">{{ ucfirst($order->status) }}</td>
                    <td class="py-2">
                        <a href="{{ route('orders.show', $order) }}" class="text-blue-600 hover:underline">Lihat</a>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        <div class="mt-4">{{ $orders->links() }}</div>
    </div>
</div>
@endsection
