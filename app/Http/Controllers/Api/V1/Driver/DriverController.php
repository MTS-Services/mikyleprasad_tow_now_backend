<?php

namespace App\Http\Controllers\Api\V1\Driver;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\DriverProfileResource;
use App\Services\DriverService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as HttpStatus;

class DriverController extends Controller
{

    public function __construct(
        private readonly DriverService $driverService
    ) {}

    public function profile(Request $request)
    {

        $driver = $this->driverService->getDriverProfile();

        if (! $driver) {
            return sendResponse(false, 'Driver profile not found.', null, HttpStatus::HTTP_NOT_FOUND);
        }

        return sendResponse(true, 'Driver profile fetched successfully.', new DriverProfileResource($driver), HttpStatus::HTTP_OK);
    }

    public function update(Request $request)
    {
        $driver = $this->driverService->updateDriverProfile($request->all());

        if (! $driver) {
            return sendResponse(false, 'Driver profile not found.', null, HttpStatus::HTTP_NOT_FOUND);
        }

        return sendResponse(true, 'Driver profile updated successfully.', new DriverProfileResource($driver), HttpStatus::HTTP_OK);
    }
}
