<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CurrencyController;
use App\Http\Controllers\Api\V1\LanguageController;
use Illuminate\Support\Facades\Route;

Route::controller(AuthController::class)->group(function () {
    Route::post('/register', 'register')->middleware(['throttle:api-register']);
    Route::post('/login', 'login')->middleware(['throttle:api-login']);
    Route::post('/two-factor-challenge', 'twoFactorChallenge')->middleware(['throttle:api-login']);
    Route::post('/forgot-password', 'forgotPassword')->middleware(['throttle:api-password-reset']);
    Route::post('/reset-password', 'resetPassword')->middleware(['throttle:api-password-reset-verify']);
});

Route::get('/languages', [LanguageController::class, 'index']);

Route::get('/currencies', [CurrencyController::class, 'index']);
