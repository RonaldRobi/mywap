<?php

use App\Http\Controllers\Api\V1\VideoController;
use Illuminate\Support\Facades\Route;

// ─── Videos (public — boleh ditonton tanpa log masuk) ─────────────────────
Route::middleware('throttle:60,1,api-videos')->group(function () {
    Route::get('/videos', [VideoController::class, 'index']);
});
