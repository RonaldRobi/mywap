<?php

use App\Http\Controllers\Api\V1\PublicHomeController;
use Illuminate\Support\Facades\Route;

// ─── Beranda awam (public — kandungan untuk tetamu tanpa log masuk) ───────
Route::middleware('throttle:60,1')->group(function () {
    Route::get('/public/home', [PublicHomeController::class, 'index']);
});
