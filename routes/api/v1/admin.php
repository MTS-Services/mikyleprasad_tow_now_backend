<?php

use App\Http\Controllers\Api\V1\Admin\AdminPortalController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/ping', function (Request $request) {
    return response()->json([
        'message' => 'ok',
        'user_id' => $request->user()->id,
        'area' => 'admin',
    ]);
});

Route::get('dashboard', [AdminPortalController::class, 'dashboard']);
Route::get('rides', [AdminPortalController::class, 'rides']);
Route::get('rides/{ride}', [AdminPortalController::class, 'showRide']);
Route::get('drivers', [AdminPortalController::class, 'drivers']);
Route::get('customers', [AdminPortalController::class, 'customers']);
