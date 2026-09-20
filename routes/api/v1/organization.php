<?php

use App\Http\Controllers\Api\V1\OrganizationInfoController;
use Illuminate\Support\Facades\Route;

// ─── Info organisasi (public — tetamu dapat organisasi utama) ─────────────
Route::middleware('throttle:60,1')->group(function () {
    Route::get('/organization/info', [OrganizationInfoController::class, 'show']);
});
