<?php

use App\Http\Controllers\Api\V1\DeviceTokenController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/device-tokens', [DeviceTokenController::class, 'store']);
    Route::delete('/device-tokens', [DeviceTokenController::class, 'destroy']);

    // Diagnostik pendaftaran token FCM (log server sahaja, tiada data sensitif).
    Route::post('/push-debug', function (Request $request) {
        Log::info('PUSH-DEBUG', [
            'user_id' => $request->user()?->id,
            'stage' => $request->input('stage'),
            'platform' => $request->input('platform'),
            'apps' => $request->input('apps'),
            'apns' => $request->input('apns'),
            'fcm' => $request->input('fcm'),
            'attempt' => $request->input('attempt'),
            'error' => $request->input('error'),
        ]);

        return response()->json(['ok' => true]);
    });
});
