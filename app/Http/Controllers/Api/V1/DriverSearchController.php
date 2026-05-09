<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\FindDriversRequest;
use App\Http\Resources\Api\V1\DriverCardResource;
use App\Enums\ApprovalStatus;
use App\Models\User;
use App\Services\DriverService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as HttpStatus;

class DriverSearchController extends Controller
{
    public function __construct(
        private readonly DriverService $driverService
    ) {}

    public function index(FindDriversRequest $request): JsonResponse
    {
        $paginator = $this->driverService->paginate($request->validated());

        return sendResponse(
            status: true,
            message: 'Drivers fetched successfully.',
            data: DriverCardResource::collection($paginator),
            statusCode: HttpStatus::HTTP_OK
        );
    }

    public function show(int $id): JsonResponse
    {
        $driver = User::query()
            ->whereKey($id)
            ->where('role', 'driver')
            ->where('approval_status', ApprovalStatus::APPROVED->value)
            ->where('is_suspended', false)
            ->with('vehicle')
            ->first();

        if (! $driver) {
            return sendResponse(false, 'Driver not found.', statusCode: HttpStatus::HTTP_NOT_FOUND);
        }

        return sendResponse(
            status: true,
            message: 'Driver fetched successfully.',
            data: new DriverCardResource($driver),
            statusCode: HttpStatus::HTTP_OK
        );
    }

    public function stats(Request $request): JsonResponse
    {
        $base = User::query()
            ->where('role', 'driver')
            ->where('approval_status', ApprovalStatus::APPROVED->value)
            ->where('is_suspended', false);

        $total = (clone $base)->count();
        $online = (clone $base)
            ->where('status', 'active')
            ->count();
        $featured = (clone $base)
            ->where('is_featured', true)
            ->count();

        return sendResponse(
            status: true,
            message: 'Driver stats fetched successfully.',
            data: [
                'total' => $total,
                'online' => $online,
                'featured' => $featured,
            ],
            statusCode: HttpStatus::HTTP_OK
        );
    }
}
