<?php

namespace App\Http\Controllers\Api\V1\Driver;

use App\Enums\ApprovalStatus;
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
            'approval_status' => ApprovalStatus::APPROVED->value,
        ];

        $drivers = $this->driverService->getAll($filter);

        return sendResponse(
            status: true,
            message: 'Approved drivers retrieved successfully',
            data: $drivers,
            statusCode: HttpStatus::HTTP_OK,
        );
    }
}
