<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\FindDriversRequest;
use App\Http\Resources\Api\V1\DriverCardResource;
use App\Services\DriverSearchService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response as HttpStatus;

class DriverSearchController extends Controller
{
    public function __construct(
        private readonly DriverSearchService $driverSearchService
    ) {}

    public function index(FindDriversRequest $request): JsonResponse
    {
        $paginator = $this->driverSearchService->paginate($request->validated());

        return sendResponse(
            status: true,
            message: 'Drivers fetched successfully.',
            data: DriverCardResource::collection($paginator),
            statusCode: HttpStatus::HTTP_OK
        );
    }
}
