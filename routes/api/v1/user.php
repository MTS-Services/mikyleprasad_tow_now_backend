<?php

use App\Http\Controllers\Api\V1\User\DashboardController;
use App\Http\Controllers\Api\V1\User\ProductController;
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
Route::get('dashboard', [RideController::class, 'dashboard']);
Route::get('rides/active', [RideController::class, 'active']);
Route::post('rides/{ride}/cancel', [RideController::class, 'cancel']);
Route::post('rides/{ride}/complete', [RideController::class, 'complete']);
Route::post('rides/{ride}/complete/approve', [RideController::class, 'approveCompletion']);
Route::apiResource('rides', RideController::class)->only(['store', 'index', 'show']);
Route::controller(DashboardController::class)->group(function () {
    Route::get('/stats', 'stats');
});
