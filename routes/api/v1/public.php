<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CurrencyController;
use App\Http\Controllers\Api\V1\Driver\DriverController;
use App\Http\Controllers\Api\V1\LanguageController;
use App\Http\Controllers\Api\V1\OtpAuthController;
use Illuminate\Support\Facades\Route;

Route::controller(AuthController::class)->group(function () {
    Route::post('/register', 'register')->middleware(['throttle:api-register']);
    Route::post('/login', 'login')->middleware(['throttle:api-login']);
    Route::post('/two-factor-challenge', 'twoFactorChallenge')->middleware(['throttle:api-login']);
    Route::post('/forgot-password', 'forgotPassword')->middleware(['throttle:api-password-reset']);
    Route::post('/reset-password', 'resetPassword')->middleware(['throttle:api-password-reset-verify']);
});

Route::controller(OtpAuthController::class)->prefix('otp')->group(function () {
    Route::post('/request', 'request')->middleware(['throttle:api-otp-request']);
    Route::post('/resend', 'request')->middleware(['throttle:api-otp-request']);
    Route::post('/verify', 'verify')->middleware(['throttle:api-otp-verify']);
    Route::post('/register/resend', 'resendRegistration')->middleware(['throttle:api-verification-otp-request']);
    Route::post('/register/verify', 'verifyRegistration')->middleware(['throttle:api-verification-otp-verify']);
});

Route::controller(DriverController::class)->prefix('driver')->group(function () {
    Route::get('/', 'index');
});

Route::get('/languages', [LanguageController::class, 'index']);

Route::get('/currencies', [CurrencyController::class, 'index']);
