<?php

use App\Http\Controllers\Api\V1\VideoController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/videos', [VideoController::class, 'index']);
});
