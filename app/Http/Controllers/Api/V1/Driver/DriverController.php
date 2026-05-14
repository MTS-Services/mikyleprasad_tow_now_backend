<?php

namespace App\Http\Controllers\Api\V1\Driver;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\UserResource;
use App\Services\UserServce;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response as HttpStatus;

class DriverController extends Controller
{

    public function __construct(
        private readonly UserServce $userServce
    ) {}


    public function update(Request $request)
    {
        $data = $this->userServce->updateProfile($request, $request->all());

        if (! $data) {
            return sendResponse(false, 'User profile not found.', null, HttpStatus::HTTP_NOT_FOUND);
        }
        return sendResponse(true, 'Data updated successfully.', new UserResource($data['user']), HttpStatus::HTTP_OK);
    }

    public function updateVehicle(Request $request)
    {
        Log::info($request->all());
        $data = $this->userServce->updateVehicle($request, $request->all());

        if (! $data) {
            return sendResponse(
                false,
                'Vehicle profile not found.',
                null,
                HttpStatus::HTTP_NOT_FOUND
            );
        }

        return sendResponse(
            true,
            'Data updated successfully.',
            new UserResource($data['user']),
            HttpStatus::HTTP_OK
        );
    }
}
