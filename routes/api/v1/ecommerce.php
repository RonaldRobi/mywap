<?php

use App\Http\Controllers\Api\V1\OrderController;
use App\Http\Controllers\Api\V1\ProductController;
use Illuminate\Support\Facades\Route;

// ─── E-commerce (produk, kategori, checkout) ────────────────────────────────
// Awam: tetamu boleh melayari produk dan membuat pesanan tanpa log masuk.
Route::middleware('throttle:60,1,api-ecommerce')->group(function () {
    Route::get('/products', [ProductController::class, 'index']);
    Route::get('/products/{product}', [ProductController::class, 'show']);
    Route::get('/categories', [ProductController::class, 'categories']);

    // Tetamu: wajib isi nama/telefon (checkoutRules); ahli: harga ahli digunakan
    // bila token Bearer hadir. Had 8/minit per IP untuk elak spam pesanan.
    Route::post('/orders', [OrderController::class, 'store'])->middleware('throttle:8,1,api-orders');
});

// ─── Pesanan ahli berdaftar ─────────────────────────────────────────────────
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/orders', [OrderController::class, 'index']);
    Route::get('/orders/{order}', [OrderController::class, 'show']);
    Route::post('/orders/{order}/pay', [OrderController::class, 'pay']);
    Route::post('/orders/{order}/receive', [OrderController::class, 'receive']);
});
