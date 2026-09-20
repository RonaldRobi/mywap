<?php

use App\Http\Controllers\Api\V1\InfaqController;
use Illuminate\Support\Facades\Route;

// ─── Infaq (public) ───────────────────────────────────────────────────────
Route::get('/infaq', [InfaqController::class, 'index']);
Route::get('/infaq/{infaq:slug}', [InfaqController::class, 'show']);

// ─── Infaq (public — tetamu boleh derma; ahli dijejaki melalui akaun) ─────
Route::post('/infaq/{infaq:slug}/donate', [InfaqController::class, 'donate'])
    ->middleware('throttle:10,1');
