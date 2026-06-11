@extends('layouts.ecommerce')
@include('nav-ecommerce')
@section('content')
<div class="container mx-auto py-6">
    <div class="flex justify-between items-center mb-4">
        <h1 class="text-2xl font-bold">Senarai Produk</h1>
        <a href="{{ route('products.create') }}" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">Tambah Produk</a>
    </div>
    @if(session('success'))
        <div class="bg-green-100 text-green-800 p-2 rounded mb-4">{{ session('success') }}</div>
    @endif
    <div class="bg-white shadow rounded p-4 overflow-x-auto">
        <table class="min-w-full">
            <thead>
                <tr>
                    <th class="py-2">#</th>
                    <th class="py-2">Nama</th>
                    <th class="py-2">Kategori</th>
                    <th class="py-2">Harga</th>
                    <th class="py-2">Stok</th>
                    <th class="py-2">Status</th>
                    <th class="py-2">Tindakan</th>
                </tr>
            </thead>
            <tbody>
                @foreach($products as $product)
                <tr>
                    <td class="py-2">{{ $loop->iteration }}</td>
                    <td class="py-2">{{ $product->name }}</td>
                    <td class="py-2">{{ $product->category->name ?? '-' }}</td>
                    <td class="py-2">RM{{ number_format($product->price, 2) }}</td>
                    <td class="py-2">{{ $product->stock }}</td>
                    <td class="py-2">
                        @if($product->status)
                            <span class="text-green-600">Aktif</span>
                        @else
                            <span class="text-gray-500">Tidak Aktif</span>
                        @endif
                    </td>
                    <td class="py-2">
                        <a href="{{ route('products.show', $product) }}" class="text-blue-600 hover:underline">Lihat</a>
                        <a href="{{ route('products.edit', $product) }}" class="text-yellow-600 hover:underline ml-2">Edit</a>
                        <form action="{{ route('products.destroy', $product) }}" method="POST" class="inline">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-red-600 hover:underline ml-2" onclick="return confirm('Padam produk ini?')">Padam</button>
                        </form>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        <div class="mt-4">{{ $products->links() }}</div>
    </div>
</div>
@endsection
