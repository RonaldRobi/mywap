<nav class="bg-white shadow mb-4">
    <div class="container mx-auto flex flex-wrap items-center justify-between py-3">
        <div class="flex items-center space-x-4">
            <a href="/dashboard" class="font-bold text-lg text-blue-700">Dashboard</a>
            <a href="{{ route('products.index') }}" class="text-gray-700 hover:text-blue-700">Produk</a>
            <a href="{{ route('categories.index') }}" class="text-gray-700 hover:text-blue-700">Kategori</a>
            <a href="{{ route('orders.index') }}" class="text-gray-700 hover:text-blue-700">Pesanan</a>
        </div>
        <div>
            <!-- Boleh tambah user dropdown/profile di sini jika perlu -->
        </div>
    </div>
</nav>
