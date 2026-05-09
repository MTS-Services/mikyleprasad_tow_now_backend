<?php

use App\Http\Controllers\Api\V1\Admin\AdminPortalController;
use App\Http\Controllers\Api\V1\Admin\RideController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;


Route::get('/ping', function (Request $request) {
    return response()->json([
        'message' => 'ok',
        'user_id' => $request->user()->id,
        'area' => 'admin',
    ]);
});

Route::controller(RideController::class)->group(function () {
    Route::get('stats', 'stats');
    Route::get('rides', 'index');
    Route::get('rides/{ride}', 'show');
    Route::get('rides/{ride}/histories', 'showHistories');
});

Route::get('drivers', [AdminPortalController::class, 'drivers']);
Route::get('customers', [AdminPortalController::class, 'customers']);
