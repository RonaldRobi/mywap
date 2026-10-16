<?php

use App\Http\Controllers\Api\V1\ManualController;
use Illuminate\Support\Facades\Route;

// ─── Manual pengguna (mengikut peranan: ahli vs admin organisasi) ─────────
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/manuals', [ManualController::class, 'index']);
});
