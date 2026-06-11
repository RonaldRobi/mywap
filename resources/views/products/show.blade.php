@extends('layouts.ecommerce')
@include('nav-ecommerce')
@section('content')
<div class="container mx-auto py-6">
    <div class="flex flex-col md:flex-row gap-6">
        <div class="md:w-1/2">
            @if($product->image)
                <img src="{{ asset('storage/' . $product->image) }}" alt="Gambar Produk" class="rounded shadow w-full h-64 object-cover mb-4">
            @endif
        </div>
        <div class="md:w-1/2">
            <h1 class="text-3xl font-bold mb-2">{{ $product->name }}</h1>
            <div class="mb-2 text-gray-600">Kategori: {{ $product->category->name ?? '-' }}</div>
            <div class="mb-2 text-xl font-semibold text-blue-700">RM{{ number_format($product->price, 2) }}</div>
            <div class="mb-2">Stok: {{ $product->stock }}</div>
            <div class="mb-4">{!! nl2br(e($product->description)) !!}</div>
            <form action="{{ route('orders.store') }}" method="POST" class="flex items-center gap-2">
                @csrf
                <input type="hidden" name="products[0][id]" value="{{ $product->id }}">
                <input type="number" name="products[0][quantity]" value="1" min="1" max="{{ $product->stock }}" class="border rounded px-2 py-1 w-20">
                <button type="submit" class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700">Beli Sekarang</button>
            </form>
        </div>
    </div>
</div>
@endsection
