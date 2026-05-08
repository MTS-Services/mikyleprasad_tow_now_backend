<?php

use App\Http\Controllers\Api\V1\Driver\RideLifecycleController;
use Illuminate\Support\Facades\Route;

Route::get('rides/incoming', [RideLifecycleController::class, 'incoming']);
Route::get('dashboard', [RideLifecycleController::class, 'dashboard']);
Route::get('rides', [RideLifecycleController::class, 'index']);
Route::post('rides/{ride}/accept', [RideLifecycleController::class, 'accept']);
Route::post('rides/{ride}/eta', [RideLifecycleController::class, 'updateEta']);
Route::post('rides/{ride}/arrived', [RideLifecycleController::class, 'arrived']);
Route::post('rides/{ride}/picked-up', [RideLifecycleController::class, 'pickedUp']);
Route::post('rides/{ride}/complete-request', [RideLifecycleController::class, 'completeRequest']);
