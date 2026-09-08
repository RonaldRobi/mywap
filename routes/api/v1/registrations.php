<?php

use App\Http\Controllers\Api\V1\EventRegistrationController;
use App\Http\Controllers\Api\V1\MemberRegistrationController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/member/registrations', [MemberRegistrationController::class, 'index']);

    // Pendaftaran program (member) — kongsi logik web melalui RegistrationService.
    Route::get('/events/{event}/registration/{form}', [EventRegistrationController::class, 'form']);
    Route::post('/events/{event}/registration', [EventRegistrationController::class, 'submit']);
});
