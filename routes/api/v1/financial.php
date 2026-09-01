<?php

use App\Http\Controllers\Api\V1\FinancialController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/member/financial/overview', [FinancialController::class, 'overview']);
});
