<?php

use App\Http\Controllers\Api\V1\Driver\DriverController;
use Illuminate\Support\Facades\Route;



Route::controller(DriverController::class)->prefix('driver')->group(function () {

});

// Route::apiResource('driver', DriverController::class);
