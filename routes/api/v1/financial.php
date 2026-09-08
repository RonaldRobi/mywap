<?php

use App\Http\Controllers\Api\V1\FinancialController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/member/financial/overview', [FinancialController::class, 'overview']);
    Route::post('/member/pay-fee', [FinancialController::class, 'payFee']);
    Route::get('/member/payments/{payment}/receipt', [FinancialController::class, 'receipt']);
});
