<?php

use App\Http\Controllers\Api\V1\ModerationController;
use Illuminate\Support\Facades\Route;

// ─── Moderation (protected — lapor & sekat UGC, ciri ahli) ────────────────
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/reports', [ModerationController::class, 'report']);

    Route::get('/blocks', [ModerationController::class, 'blocks']);
    Route::post('/blocks', [ModerationController::class, 'block']);
    Route::delete('/blocks/{user}', [ModerationController::class, 'unblock']);
});
