<?php

use App\Http\Controllers\Api\V1\MediaStatementController;
use Illuminate\Support\Facades\Route;

Route::middleware('throttle:60,1,api-media-statements')->group(function () {
    Route::get('/media-statements', [MediaStatementController::class, 'index'])->name('api.v1.media-statements.index');
    Route::get('/media-statements/{mediaStatement}', [MediaStatementController::class, 'show'])->name('api.v1.media-statements.show');
});
