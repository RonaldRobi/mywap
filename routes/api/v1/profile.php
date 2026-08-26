<?php

use App\Http\Controllers\Api\V1\ProfileController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/profile', [ProfileController::class, 'show']);
    Route::get('/profile/complete', [ProfileController::class, 'complete']);
    Route::post('/profile/complete', [ProfileController::class, 'storeComplete']);
    Route::put('/profile', [ProfileController::class, 'update']);
    Route::get('/profile/edit-meta', [ProfileController::class, 'editMeta']);
});
