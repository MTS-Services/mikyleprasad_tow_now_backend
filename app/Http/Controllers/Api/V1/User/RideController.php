<?php

namespace App\Http\Controllers\Api\V1\User;

use App\Enums\RideStatusEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Ride\CancelRideRequest;
use App\Http\Requests\Api\V1\Ride\StoreRideRequest;
use App\Http\Resources\Api\V1\RideResource;
use App\Models\Ride;
use App\Services\RideLifecycleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response as HttpStatus;
use Symfony\Component\HttpKernel\Exception\HttpException;

class RideController extends Controller
{
    public function __construct(
        private readonly RideLifecycleService $rideLifecycleService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['sometimes', 'array'],
            'status.*' => ['string'],
            'q' => ['sometimes', 'string'],
            'from' => ['sometimes', 'date'],
            'to' => ['sometimes', 'date'],
            'sort' => ['sometimes', 'in:latest,oldest'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ]);

        $rides = $this->rideLifecycleService->getRides($request->user(), $validated);

        return sendResponse(
            status: true,
            message: 'Ride history fetched successfully.',
            data: RideResource::collection($rides),
            statusCode: HttpStatus::HTTP_OK
        );
    }

    public function store(StoreRideRequest $request): JsonResponse
    {
        try {
            $ride = $this->rideLifecycleService->createRequest($request->user(), $request->validated());

            return sendResponse(
                status: true,
                message: 'Ride request created successfully.',
                data: new RideResource($ride),
                statusCode: HttpStatus::HTTP_CREATED
            );
        } catch (HttpException $e) {
            return sendResponse(
                status: false,
                message: $e->getMessage(),
                statusCode: $e->getStatusCode()
            );
        }
    }

    public function active(Request $request): JsonResponse
    {
        $ride = Ride::query()
            ->where('user_id', $request->user()->id)
            ->whereNotIn('status', [
                RideStatusEnum::SYSTEM_CANCELLED->value,
                RideStatusEnum::COMPLETED_USER->value,
                RideStatusEnum::CANCELLED_BY_USER->value,
                RideStatusEnum::CANCELLED_BY_DRIVER->value,
                RideStatusEnum::EXPIRED->value,
            ])
            ->with(['driver', 'user', 'conversation'])
            ->latest('id')
            ->first();

        return sendResponse(
            status: true,
            message: 'Active ride fetched successfully.',
            data: $ride ? new RideResource($ride) : null,
            statusCode: HttpStatus::HTTP_OK
        );
    }

    public function show(Request $request, Ride $ride): JsonResponse
    {
        if ($ride->user_id !== $request->user()->id || $ride->status === RideStatusEnum::SYSTEM_CANCELLED) {
            return sendResponse(false, 'Ride not found.', statusCode: HttpStatus::HTTP_NOT_FOUND);
        }

        $ride->load(['driver', 'user', 'conversation']);

        return sendResponse(
            status: true,
            message: 'Ride details fetched successfully.',
            data: new RideResource($ride),
            statusCode: HttpStatus::HTTP_OK
        );
    }

    public function cancel(CancelRideRequest $request, Ride $ride): JsonResponse
    {
        $ride = $this->rideLifecycleService->cancelByUser($ride->load(['user', 'driver', 'conversation']), $request->user(), $request->validated('reason'));

        return sendResponse(
            status: true,
            message: 'Ride cancelled successfully.',
            data: new RideResource($ride),
            statusCode: HttpStatus::HTTP_OK
        );
    }

    public function complete(Request $request, Ride $ride): JsonResponse
    {
        $ride = $this->rideLifecycleService->completeByUser($ride->load(['user', 'driver', 'conversation']), $request->user());

        return sendResponse(
            status: true,
            message: 'Ride completed successfully.',
            data: new RideResource($ride),
            statusCode: HttpStatus::HTTP_OK
        );
    }

    public function approveCompletion(Request $request, Ride $ride): JsonResponse
    {
        $ride = $this->rideLifecycleService->completeByUser($ride->load(['user', 'driver', 'conversation']), $request->user());

        return sendResponse(
            status: true,
            message: 'Ride completion approved.',
            data: new RideResource($ride),
            statusCode: HttpStatus::HTTP_OK
        );
    }

    public function updateStatus(Request $request, string $rideId): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::enum(RideStatusEnum::class)],
        ]);

        try {
            $ride = $this->rideLifecycleService->getRide($rideId, 'id');
            $user = $request->user();
            $status = RideStatusEnum::from($validated['status']);
            $ride = $this->rideLifecycleService->updateStatus($ride, $user, $status);

            return sendResponse(
                status: true,
                message: 'Ride status updated successfully.',
                data: new RideResource($ride),
                statusCode: HttpStatus::HTTP_OK
            );
        } catch (HttpException $e) {
            return sendResponse(
                status: false,
                message: $e->getMessage(),
                statusCode: $e->getStatusCode()
            );
        }
    }
}
