<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Services\DriverServce;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as HttpStatus;

class DriverController extends Controller
{
     private DriverServce $driverService;

    public function __construct(DriverServce $driverService)
    {
        $this->driverService = $driverService;
    }

    public function index(Request $request): JsonResponse
    {



        $filter = [
            'search' => $request->input('search') ?? null,
            'status' => $request->input('status') ?? null,
            'is_suspended' => $request->input('is_suspended') ?? null,
            'is_featured' => $request->input('is_featured') ?? null,
            'approval_status' => $request->input('approval_status') ?? null,
        ];

        $drivers = $this->driverService->getAll($filter);

        // dd($drivers);

        return sendResponse(
            status: true,
            message: 'Drivers retrieved successfully',
            data: $drivers,
            statusCode: HttpStatus::HTTP_OK,
        );
    }
}
