<?php

use App\Http\Controllers\Api\V1\AdminController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/admin/dashboard', [AdminController::class, 'dashboard']);
    Route::get('/admin/members', [AdminController::class, 'members']);
    Route::get('/admin/fees', [AdminController::class, 'fees']);
    Route::get('/admin/attendance/registrations', [AdminController::class, 'attendance']);
    Route::post('/admin/attendance/scan', [AdminController::class, 'scan']);
    Route::post('/admin/broadcast', [AdminController::class, 'broadcast']);
});
