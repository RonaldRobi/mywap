<?php

use App\Http\Controllers\Api\V1\InfaqController;
use Illuminate\Support\Facades\Route;

// ─── Infaq (public) ───────────────────────────────────────────────────────
Route::get('/infaq', [InfaqController::class, 'index']);
Route::get('/infaq/{infaq:slug}', [InfaqController::class, 'show']);

// ─── Infaq (protected — member donates while logged in) ───────────────────
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/infaq/{infaq:slug}/donate', [InfaqController::class, 'donate']);
});
