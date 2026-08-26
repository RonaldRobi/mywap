<?php

use App\Http\Controllers\Api\V1\UsrahController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/usrah', [UsrahController::class, 'index']);
});
