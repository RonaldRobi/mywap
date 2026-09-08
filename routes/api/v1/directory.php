<?php

use App\Http\Controllers\Api\V1\ChatController;
use App\Http\Controllers\Api\V1\DirectoryController;
use App\Http\Controllers\Api\V1\NotificationController;
use Illuminate\Support\Facades\Route;

Route::get('/card/{memberNo}', [DirectoryController::class, 'card']);

Route::middleware('auth:sanctum')->group(function () {
    // Ahli-directory hanya untuk pentadbir (web sahaja) — privacy ahli biasa.
    Route::get('/directory', [DirectoryController::class, 'index'])
        ->middleware('api_admin');
    Route::post('/chat', [ChatController::class, 'send']);
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::post('/notifications/read-all', [NotificationController::class, 'readAll']);
});
