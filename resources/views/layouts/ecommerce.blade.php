<div class="h-screen flex">
    <aside class="w-64 bg-white border-r shadow flex flex-col">
        <div class="p-6 font-bold text-blue-700 text-xl border-b">E-Commerce</div>
        <nav class="flex-1 p-4 space-y-2">
            <a href="/dashboard" class="block px-3 py-2 rounded hover:bg-blue-50 text-gray-700 font-medium">Dashboard</a>
            <a href="{{ route('products.index') }}" class="block px-3 py-2 rounded hover:bg-blue-50 text-gray-700">Produk</a>
            <a href="{{ route('categories.index') }}" class="block px-3 py-2 rounded hover:bg-blue-50 text-gray-700">Kategori</a>
            <a href="{{ route('orders.index') }}" class="block px-3 py-2 rounded hover:bg-blue-50 text-gray-700">Pesanan</a>
        </nav>
    </aside>
    <main class="flex-1 min-h-screen bg-gray-50">
        @yield('content')
    </main>
</div>
