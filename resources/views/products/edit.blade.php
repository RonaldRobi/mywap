@extends('layouts.ecommerce')
@include('nav-ecommerce')
@section('content')
<div class="container mx-auto py-6">
    <h1 class="text-2xl font-bold mb-4">Edit Produk</h1>
    <form action="{{ route('products.update', $product) }}" method="POST" enctype="multipart/form-data" class="bg-white shadow rounded p-4">
        @csrf
        @method('PUT')
        <div class="mb-4">
            <label class="block mb-1 font-semibold">Nama</label>
            <input type="text" name="name" class="w-full border rounded px-3 py-2" required value="{{ old('name', $product->name) }}">
            @error('name')<div class="text-red-600 text-sm">{{ $message }}</div>@enderror
        </div>
        <div class="mb-4">
            <label class="block mb-1 font-semibold">Kategori</label>
            <select name="category_id" class="w-full border rounded px-3 py-2" required>
                <option value="">Pilih Kategori</option>
                @foreach($categories as $category)
                    <option value="{{ $category->id }}" @if(old('category_id', $product->category_id) == $category->id) selected @endif>{{ $category->name }}</option>
                @endforeach
            </select>
            @error('category_id')<div class="text-red-600 text-sm">{{ $message }}</div>@enderror
        </div>
        <div class="mb-4">
            <label class="block mb-1 font-semibold">Harga (RM)</label>
            <input type="number" step="0.01" name="price" class="w-full border rounded px-3 py-2" required value="{{ old('price', $product->price) }}">
            @error('price')<div class="text-red-600 text-sm">{{ $message }}</div>@enderror
        </div>
        <div class="mb-4">
            <label class="block mb-1 font-semibold">Stok</label>
            <input type="number" name="stock" class="w-full border rounded px-3 py-2" required value="{{ old('stock', $product->stock) }}">
            @error('stock')<div class="text-red-600 text-sm">{{ $message }}</div>@enderror
        </div>
        <div class="mb-4">
            <label class="block mb-1 font-semibold">Deskripsi</label>
            <textarea name="description" class="w-full border rounded px-3 py-2">{{ old('description', $product->description) }}</textarea>
            @error('description')<div class="text-red-600 text-sm">{{ $message }}</div>@enderror
        </div>
        <div class="mb-4">
            <label class="block mb-1 font-semibold">Gambar</label>
            <input type="file" name="image" class="w-full border rounded px-3 py-2">
            @if($product->image)
                <img src="{{ asset('storage/' . $product->image) }}" alt="Gambar Produk" class="h-16 mt-2">
            @endif
            @error('image')<div class="text-red-600 text-sm">{{ $message }}</div>@enderror
        </div>
        <div class="mb-4">
            <label class="inline-flex items-center">
                <input type="checkbox" name="status" class="form-checkbox" @if($product->status) checked @endif> Aktif
            </label>
        </div>
        <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">Kemaskini</button>
        <a href="{{ route('products.index') }}" class="ml-2 text-gray-600">Batal</a>
    </form>
</div>
@endsection
