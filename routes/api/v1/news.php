<?php

use App\Http\Controllers\Api\V1\NewsController;
use Illuminate\Support\Facades\Route;

// ─── Info Terkini (public — boleh dibaca tanpa log masuk) ─────────────────
Route::middleware('throttle:60,1')->group(function () {
    Route::get('/news', [NewsController::class, 'index']);
    Route::get('/news/{post}', [NewsController::class, 'show']);
});

// ─── Info Terkini (protected — reaksi & komen perlu log masuk) ────────────
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/news/{post}/react', [NewsController::class, 'react']);
    Route::post('/news/{post}/comments', [NewsController::class, 'storeComment']);
});
