<?php

use App\Http\Controllers\Api\V1\Admin\AdminPortalController;
use App\Http\Controllers\Api\V1\Admin\ProfileController;
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
});

Route::get('drivers', [AdminPortalController::class, 'drivers']);
Route::post('drivers/{driver}/accept', [AdminPortalController::class, 'acceptDriver']);
Route::post('drivers/{driver}/reject', [AdminPortalController::class, 'rejectDriver']);
Route::get('drivers/{driver}', [AdminPortalController::class, 'showDriver']);
Route::get('customers', [AdminPortalController::class, 'customers']);
Route::get('customers/{customer}', [AdminPortalController::class, 'showCustomer']);
Route::get('reviews', [AdminPortalController::class, 'reviews']);


Route::controller(ProfileController::class)->group(function () {
    Route::get('profile', 'index');
    Route::post('profile/update', 'update');
});

