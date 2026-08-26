<?php

use App\Http\Controllers\Api\V1\MemberRegistrationController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/member/registrations', [MemberRegistrationController::class, 'index']);
});
