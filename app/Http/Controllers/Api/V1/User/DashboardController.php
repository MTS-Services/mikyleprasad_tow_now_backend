<?php

namespace App\Http\Controllers\Api\V1\User;

use App\Http\Controllers\Controller;
use App\Services\RideLifecycleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as HttpStatus;

class DashboardController extends Controller
{
    public function __construct(
        private readonly RideLifecycleService $rideLifecycleService,
    ) {}


    public function stats(Request $request): JsonResponse
    {
        $stats = $this->rideLifecycleService->getStats($request->user());

        return sendResponse(
            status: true,
            message: 'Dashboard stats fetched successfully.',
            data: [
                'summary' => $stats,
            ],
            statusCode: HttpStatus::HTTP_OK
        );
    }
}
