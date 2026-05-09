<?php

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

Route::prefix('rides')->controller(RideController::class)->group(function () {
    Route::get('/stats', 'stats');
    Route::get('/', 'index');
    Route::post('/', 'store');
    Route::get('/active', 'active');
    Route::get('/{ride}', 'show');
    Route::post('/{ride}/cancel', 'cancel');
    Route::post('/{ride}/complete', 'complete');
});
