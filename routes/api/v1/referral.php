<?php

use App\Http\Controllers\Api\V1\ReferralController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/member/referral', [ReferralController::class, 'index']);
});
