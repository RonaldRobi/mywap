@extends('layouts.ecommerce')
@section('content')
<div class="container mx-auto py-6">
    <div class="flex justify-between items-center mb-4">
        <h1 class="text-2xl font-bold">Senarai Kategori</h1>
        <a href="{{ route('categories.create') }}" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">Tambah Kategori</a>
    </div>
    @if(session('success'))
        <div class="bg-green-100 text-green-800 p-2 rounded mb-4">{{ session('success') }}</div>
    @endif
    <div class="bg-white shadow rounded p-4">
        <table class="min-w-full">
            <thead>
                <tr>
                    <th class="py-2">#</th>
                    <th class="py-2">Nama</th>
                    <th class="py-2">Deskripsi</th>
                    <th class="py-2">Tindakan</th>
                </tr>
            </thead>
            <tbody>
                @foreach($categories as $category)
                <tr>
                    <td class="py-2">{{ $loop->iteration }}</td>
                    <td class="py-2">{{ $category->name }}</td>
                    <td class="py-2">{{ $category->description }}</td>
                    <td class="py-2">
                        <a href="{{ route('categories.edit', $category) }}" class="text-blue-600 hover:underline">Edit</a>
                        <form action="{{ route('categories.destroy', $category) }}" method="POST" class="inline">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-red-600 hover:underline ml-2" onclick="return confirm('Padam kategori ini?')">Padam</button>
                        </form>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
