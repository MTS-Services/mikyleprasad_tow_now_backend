<?php

use App\Http\Controllers\Api\V1\Driver\RideLifecycleController;
use Illuminate\Support\Facades\Route;

Route::controller(RideLifecycleController::class)->group(function () {
    Route::get('dashboard', 'dashboard');
    Route::prefix('rides')->group(function () {
        Route::get('/', 'index');
        Route::get('/incoming', 'incoming');
        Route::post('/{ride}/accept', 'accept');
        Route::post('/{ride}/eta', 'updateEta');
        Route::post('/{ride}/cancel', 'cancel');
        Route::post('/{ride}/complete-request', 'completeRequest');
    });
});
