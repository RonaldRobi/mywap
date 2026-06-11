@extends('layouts.ecommerce')
@include('nav-ecommerce')
@section('content')
<div class="container mx-auto py-6">
    <h1 class="text-2xl font-bold mb-4">Edit Kategori</h1>
    <form action="{{ route('categories.update', $category) }}" method="POST" class="bg-white shadow rounded p-4">
        @csrf
        @method('PUT')
        <div class="mb-4">
            <label class="block mb-1 font-semibold">Nama</label>
            <input type="text" name="name" class="w-full border rounded px-3 py-2" required value="{{ old('name', $category->name) }}">
            @error('name')<div class="text-red-600 text-sm">{{ $message }}</div>@enderror
        </div>
        <div class="mb-4">
            <label class="block mb-1 font-semibold">Deskripsi</label>
            <textarea name="description" class="w-full border rounded px-3 py-2">{{ old('description', $category->description) }}</textarea>
            @error('description')<div class="text-red-600 text-sm">{{ $message }}</div>@enderror
        </div>
        <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">Kemaskini</button>
        <a href="{{ route('categories.index') }}" class="ml-2 text-gray-600">Batal</a>
    </form>
</div>
@endsection
