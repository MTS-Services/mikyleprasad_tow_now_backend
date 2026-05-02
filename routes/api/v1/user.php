<?php

use App\Http\Controllers\Api\V1\User\ProductController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/ping', function (Request $request) {
    return response()->json([
        'message' => 'ok',
        'user_id' => $request->user()->id,
    ]);
});

Route::apiResource('products', ProductController::class);
