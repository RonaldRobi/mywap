<?php

use App\Http\Controllers\Api\V1\OrganizationInfoController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/organization/info', [OrganizationInfoController::class, 'show']);
});
