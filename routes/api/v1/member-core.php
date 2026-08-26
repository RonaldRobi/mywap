<?php

use App\Http\Controllers\Api\V1\MemberCoreController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->prefix('member')->group(function () {
    Route::get('/card', [MemberCoreController::class, 'card']);
    Route::get('/fee-status', [MemberCoreController::class, 'feeStatus']);

    Route::get('/announcements', [MemberCoreController::class, 'announcements']);
    Route::post('/announcements/{announcement}/react', [MemberCoreController::class, 'react']);
    Route::post('/announcements/{announcement}/read', [MemberCoreController::class, 'markRead']);

    Route::get('/library', [MemberCoreController::class, 'library']);
});
