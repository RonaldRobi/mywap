<?php

use App\Http\Controllers\Api\MemberSearchController;
use Illuminate\Support\Facades\Route;

Route::middleware(['throttle:30,1'])->group(function () {
    Route::get('/members/search', [MemberSearchController::class, 'search']);
});
