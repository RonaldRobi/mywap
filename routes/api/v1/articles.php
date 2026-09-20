<?php

use App\Http\Controllers\Api\V1\ArticleController;
use Illuminate\Support\Facades\Route;

// ─── Articles (public — boleh dibaca tanpa log masuk) ─────────────────────
Route::middleware('throttle:60,1')->group(function () {
    Route::get('/articles', [ArticleController::class, 'index']);
    Route::get('/articles/{article}', [ArticleController::class, 'show']);
});

// ─── Articles (protected — reaksi & komen perlu log masuk) ────────────────
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/articles/{article}/react', [ArticleController::class, 'react']);
    Route::post('/articles/{article}/comments', [ArticleController::class, 'storeComment']);
});
