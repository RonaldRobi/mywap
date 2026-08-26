<?php

use App\Http\Controllers\Api\V1\FormController;
use Illuminate\Support\Facades\Route;

// Borang public — boleh diakses tanpa login (client Flutter isi borang luaran).
Route::get('/forms/{token}', [FormController::class, 'show']);
Route::post('/forms/{token}/submit', [FormController::class, 'submit']);
