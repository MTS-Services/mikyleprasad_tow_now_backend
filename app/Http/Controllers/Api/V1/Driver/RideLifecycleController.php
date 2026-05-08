<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Driver;

use App\Enums\RideStatusEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Ride\AcceptRideRequest;
use App\Http\Requests\Api\V1\Ride\UpdateRideEtaRequest;
use App\Http\Resources\Api\V1\RideResource;
use App\Models\Ride;
use App\Services\RideLifecycleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as HttpStatus;

class RideLifecycleController extends Controller
{
    public function __construct(
        private readonly RideLifecycleService $rideLifecycleService
    ) {}

    public function incoming(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);
        $perPage = (int) ($validated['per_page'] ?? 15);

        $rides = Ride::query()
            ->where('driver_id', $request->user()->id)
            ->where('status', RideStatusEnum::REQUESTED->value)
            ->where('status', '!=', RideStatusEnum::SYSTEM_CANCELLED->value)
            ->with(['user', 'driver', 'conversation'])
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString();

        return sendResponse(
            true,
            'Incoming rides fetched successfully.',
            RideResource::collection($rides),
            HttpStatus::HTTP_OK
        );
    }

    public function accept(AcceptRideRequest $request, Ride $ride): JsonResponse
    {
        $ride = $this->rideLifecycleService->acceptByDriver(
            $ride->load(['user', 'driver', 'conversation']),
            $request->user(),
            (int) $request->validated('eta_minutes')
        );

        return sendResponse(true, 'Ride accepted successfully.', new RideResource($ride), HttpStatus::HTTP_OK);
    }

    public function updateEta(UpdateRideEtaRequest $request, Ride $ride): JsonResponse
    {
        $ride = $this->rideLifecycleService->updateEta(
            $ride->load(['user', 'driver', 'conversation']),
            $request->user(),
            (int) $request->validated('eta_minutes'),
            (string) $request->validated('reason')
        );

        return sendResponse(true, 'Ride ETA updated successfully.', new RideResource($ride), HttpStatus::HTTP_OK);
    }

    public function arrived(Request $request, Ride $ride): JsonResponse
    {
        $ride = $this->rideLifecycleService->markArrived($ride->load(['user', 'driver', 'conversation']), $request->user());

        return sendResponse(true, 'Ride marked as arrived.', new RideResource($ride), HttpStatus::HTTP_OK);
    }

    public function pickedUp(Request $request, Ride $ride): JsonResponse
    {
        $ride = $this->rideLifecycleService->markPickedUp($ride->load(['user', 'driver', 'conversation']), $request->user());

        return sendResponse(true, 'Ride marked as picked up.', new RideResource($ride), HttpStatus::HTTP_OK);
    }

    public function completeRequest(Request $request, Ride $ride): JsonResponse
    {
        $ride = $this->rideLifecycleService->requestCompletionByDriver($ride->load(['user', 'driver', 'conversation']), $request->user());

        return sendResponse(true, 'Ride completion approval requested.', new RideResource($ride), HttpStatus::HTTP_OK);
    }
}
