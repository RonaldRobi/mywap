<?php

use App\Http\Controllers\Api\V1\PollController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/polls', [PollController::class, 'index']);
    Route::get('/polls/{poll}', [PollController::class, 'show']);
    Route::post('/polls/{poll}/respond', [PollController::class, 'respond']);
    Route::get('/polls/{poll}/results', [PollController::class, 'results']);
});
