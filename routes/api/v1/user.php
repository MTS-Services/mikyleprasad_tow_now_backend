<?php

use App\Http\Controllers\Api\V1\User\ProductController;
use App\Http\Controllers\Api\V1\User\ProfileController;
use App\Http\Controllers\Api\V1\User\ReviewController;
use App\Http\Controllers\Api\V1\User\RideController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/ping', function (Request $request) {
    return response()->json([
        'message' => 'ok',
        'user_id' => $request->user()->id,
    ]);
});

Route::apiResource('products', ProductController::class);

Route::get('/stats', [RideController::class, 'stats']);
Route::prefix('rides')->controller(RideController::class)->group(function () {
    Route::get('/', 'index');
    Route::post('/', 'store');
    Route::post('/offline-sync', 'offlineSync')->middleware('throttle:ride-offline-sync');
    Route::get('/active', 'active');
    Route::get('/{ride}/track', 'track')->middleware('throttle:ride-track');
    Route::get('/{ride}/status', 'track')->middleware('throttle:ride-track');
    Route::get('/{ride}', 'show');
    Route::post('/{ride}/cancel', 'cancel');
    Route::post('/{ride}/arrived', 'markArrived');
    Route::post('/{ride}/complete', 'complete');
});

Route::controller(ProfileController::class)->group(function () {
    Route::get('profile', 'profile');
    Route::post('profile/update', 'update');
});

Route::controller(ReviewController::class)->group(function () {
    Route::post('reviews/{ride_id}', 'store');
});
