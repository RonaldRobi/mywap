<?php

use App\Http\Controllers\Api\V1\NewsController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/news', [NewsController::class, 'index']);
    Route::get('/news/{post}', [NewsController::class, 'show']);
    Route::post('/news/{post}/react', [NewsController::class, 'react']);
    Route::post('/news/{post}/comments', [NewsController::class, 'storeComment']);
});
