<?php

use App\Http\Controllers\Api\V1\Driver\DriverController;
use App\Http\Controllers\Api\V1\Driver\DriverLocationController;
use App\Http\Controllers\Api\V1\Driver\RideController;
use Illuminate\Support\Facades\Route;

Route::put('location', [DriverLocationController::class, 'update'])->middleware('throttle:driver-location');

Route::controller(RideController::class)->group(function () {
    Route::get('stats', 'stats');
    Route::prefix('rides')->group(function () {
        Route::get('/', 'index');
        Route::get('/incoming', 'incoming');
        Route::get('/active', 'activeRide');
        Route::post('/{ride}/accept', 'accept');
        Route::post('/{ride}/eta', 'updateEta');
        Route::post('/{ride}/arrived', 'markArrived');
        Route::post('/{ride}/cancel', 'cancel');
        Route::post('/{ride}/complete-request', 'completeRequest');
        Route::get('/{ride}', 'show');
    });
});

Route::controller(DriverController::class)->group(function () {
    Route::get('profile', 'profile');
    Route::post('profile/update', 'update');
    Route::post('profile/update-vehicle', 'updateVehicle');
    Route::get('reviews', 'reviews');
});
