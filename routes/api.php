<?php

use App\Http\Controllers\Api\MemberSearchController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\AppConfigController;
use App\Http\Controllers\Api\V1\EventController;
use App\Http\Controllers\Api\V1\MemberDashboardController;
use App\Http\Controllers\Api\V1\OnboardingController;
use Illuminate\Support\Facades\Route;

Route::middleware(['throttle:30,1'])->group(function () {
    Route::get('/members/search', [MemberSearchController::class, 'search']);
});

/*
|--------------------------------------------------------------------------
| REST API — /api/v1/* (client: Flutter mobile + web)
|--------------------------------------------------------------------------
| Konvensyen: docs/FLUTTER_PLAN.md §8 — envelope { data, meta },
| pagination §8.2, error §8.3, auth Bearer §8.4.
|
| Setiap domain meletakkan route dalam fail sendiri (routes/api/v1/*.php)
| supaya agent selari boleh bekerja tanpa konflik fail. Fail domain itu
| boleh isytihar group middleware (auth:sanctum) sendiri.
*/
Route::prefix('v1')->group(function () {
    Route::get('/onboarding', [OnboardingController::class, 'index'])->middleware('throttle:30,1');
    Route::get('/app-config', [AppConfigController::class, 'index'])->middleware('throttle:30,1');
    // ─── Auth (public) ───────────────────────────────────────────────────
    Route::post('/auth/login', [AuthController::class, 'login'])
        ->middleware('throttle:5,1');
    Route::post('/auth/register', [AuthController::class, 'register'])
        ->middleware('throttle:10,1');
    Route::get('/auth/referral/{code}', [AuthController::class, 'resolveReferral'])
        ->middleware('throttle:30,1');
    Route::post('/auth/check-member', [AuthController::class, 'checkMember'])
        ->middleware('throttle:15,1');
    Route::post('/auth/forgot-id', [AuthController::class, 'forgotId'])
        ->middleware('throttle:5,1');
    Route::post('/auth/forgot-password', [AuthController::class, 'forgotPassword'])
        ->middleware('throttle:5,1');
    Route::post('/auth/reset-password', [AuthController::class, 'resetPassword'])
        ->middleware('throttle:5,1');
    Route::post('/auth/verify-identity', [AuthController::class, 'verifyIdentity'])
        ->middleware('throttle:5,1');
    Route::post('/auth/send-otp', [AuthController::class, 'sendOtp'])
        ->middleware('throttle:5,1');
    Route::post('/auth/update-and-send-otp', [AuthController::class, 'updateAndSendOtp'])
        ->middleware('throttle:5,1');
    Route::post('/auth/verify-otp', [AuthController::class, 'verifyOtp'])
        ->middleware('throttle:10,1');

    // ─── Protected (Fasa 0 template) ─────────────────────────────────────
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/auth/me', [AuthController::class, 'me']);

        // Member core
        Route::get('/member/dashboard', [MemberDashboardController::class, 'index']);

        // Events
        Route::get('/events', [EventController::class, 'index']);
        Route::get('/events/{event}', [EventController::class, 'show']);
        Route::post('/events/{event}/rsvp', [EventController::class, 'rsvp']);
        Route::post('/events/{id}/check-in', [EventController::class, 'checkIn']);
    });

    // ─── Route fail per-domain ───────────────────────────────────────────
    foreach (glob(__DIR__.'/api/v1/*.php') ?: [] as $routeFile) {
        require $routeFile;
    }
});
