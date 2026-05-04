<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\ContactVerificationOtpController;
use App\Http\Controllers\Api\V1\ConversationController;
use App\Http\Controllers\Api\V1\LoginHistoryController;
use App\Http\Controllers\Api\V1\MessageController;
use App\Http\Controllers\Api\V1\TwoFactorApiController;
use App\Http\Controllers\Api\V1\UserController;
use App\Http\Controllers\Api\V1\UserNotificationController;
use App\Http\Controllers\Api\V1\UserPreferencesController;
use Illuminate\Support\Facades\Route;

Route::controller(AuthController::class)->group(function () {
    Route::post('/logout', 'logout');
});

Route::controller(ContactVerificationOtpController::class)->prefix('verification/otp')->group(function () {
    Route::post('/request', 'request')->middleware(['throttle:api-verification-otp-request']);
    Route::post('/verify', 'verify')->middleware(['throttle:api-verification-otp-verify']);
});

Route::controller(UserController::class)->group(function () {
    Route::get('/me', 'me');
});

Route::get('/login-history', [LoginHistoryController::class, 'index']);

Route::controller(TwoFactorApiController::class)->prefix('two-factor')->group(function () {
    Route::post('/reauthentication/otp', 'sendReauthenticationOtp')->middleware(['throttle:api-sensitive-action-otp-request']);
    Route::post('/enable', 'enable');
    Route::get('/qr-code', 'qrCode');
    Route::post('/confirm', 'confirm');
    Route::get('/recovery-codes', 'recoveryCodes');
    Route::post('/recovery-codes/regenerate', 'regenerateRecoveryCodes');
    Route::delete('/disable', 'disable');
});

Route::patch('/preferences', [UserPreferencesController::class, 'update']);

Route::controller(UserNotificationController::class)->prefix('notifications')->group(function () {
    Route::get('/', 'index');
    Route::post('/read-all', 'markAllRead');
    Route::post('/test-broadcast', 'storeTest');
    Route::post('/{id}/read', 'markAsRead');
});

Route::controller(ConversationController::class)->prefix('conversations')->group(function () {
    Route::get('/', 'index');
    Route::post('/', 'store');
    Route::get('/{conversation}', 'show');
    Route::patch('/{conversation}', 'update');
    Route::post('/{conversation}/participants', 'addParticipants');
});

Route::controller(MessageController::class)->prefix('conversations/{conversation}/messages')->group(function () {
    Route::get('/', 'index');
    Route::post('/', 'store');
});
