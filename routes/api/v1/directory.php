<?php

use App\Http\Controllers\Api\V1\ChatController;
use App\Http\Controllers\Api\V1\DirectoryController;
use App\Http\Controllers\Api\V1\NotificationController;
use Illuminate\Support\Facades\Route;

Route::get('/card/{memberNo}', [DirectoryController::class, 'card']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/directory', [DirectoryController::class, 'index']);
    Route::post('/chat', [ChatController::class, 'send']);
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::post('/notifications/read-all', [NotificationController::class, 'readAll']);
});
