<?php

use App\Http\Controllers\Api\V1\FacilityController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/facilities', [FacilityController::class, 'index']);
    Route::get('/facilities/{facility}', [FacilityController::class, 'show']);
    Route::post('/facilities/{facility}/book', [FacilityController::class, 'book']);
});
