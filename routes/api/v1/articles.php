<?php

use App\Http\Controllers\Api\V1\ArticleController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/articles', [ArticleController::class, 'index']);
    Route::get('/articles/{article}', [ArticleController::class, 'show']);
    Route::post('/articles/{article}/react', [ArticleController::class, 'react']);
    Route::post('/articles/{article}/comments', [ArticleController::class, 'storeComment']);
});
