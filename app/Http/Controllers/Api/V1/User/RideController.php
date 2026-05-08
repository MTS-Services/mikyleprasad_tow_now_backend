<?php

namespace App\Http\Controllers\Api\V1\User;

use App\Enums\RideStatusEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Ride\CancelRideRequest;
use App\Http\Requests\Api\V1\Ride\StoreRideRequest;
use App\Http\Resources\Api\V1\RideResource;
use App\Models\Ride;
use App\Services\RideLifecycleService;
use App\Support\Filters\RideQueryFilters;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as HttpStatus;

class RideController extends Controller
{
    public function __construct(
        private readonly RideLifecycleService $rideLifecycleService,
        private readonly RideQueryFilters $rideQueryFilters,
    ) {}

    public function store(StoreRideRequest $request): JsonResponse
    {
        $ride = $this->rideLifecycleService->createRequest($request->user(), $request->validated());

        return sendResponse(
            status: true,
            message: 'Ride request created successfully.',
            data: new RideResource($ride),
            statusCode: HttpStatus::HTTP_CREATED
        );
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
        ]);
        $perPage = (int) ($validated['per_page'] ?? 15);

        $query = Ride::query()
            ->where('user_id', $request->user()->id)
            ->where('status', '!=', RideStatusEnum::SYSTEM_CANCELLED->value)
            ->with(['driver', 'conversation'])
            ->orderByDesc('created_at');

        $rides = $this->rideQueryFilters
            ->apply($query, $validated)
            ->paginate($perPage)
            ->withQueryString();

        return sendResponse(
            status: true,
            message: 'Ride history fetched successfully.',
            data: RideResource::collection($rides),
            statusCode: HttpStatus::HTTP_OK
        );
    }

    public function dashboard(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $summary = [
            'active' => Ride::query()->where('user_id', $userId)->whereIn('status', [
                RideStatusEnum::REQUESTED->value,
                RideStatusEnum::ACCEPTED->value,
                RideStatusEnum::ARRIVED->value,
                RideStatusEnum::PICKED_UP->value,
                RideStatusEnum::COMPLETED_DRIVER_PENDING_USER->value,
            ])->count(),
            'completed' => Ride::query()->where('user_id', $userId)->where('status', RideStatusEnum::COMPLETED_USER->value)->count(),
            'cancelled_or_expired' => Ride::query()->where('user_id', $userId)->whereIn('status', [
                RideStatusEnum::CANCELLED_BY_DRIVER->value,
                RideStatusEnum::CANCELLED_BY_USER->value,
                RideStatusEnum::EXPIRED->value,
            ])->count(),
            'total' => Ride::query()->where('user_id', $userId)->where('status', '!=', RideStatusEnum::SYSTEM_CANCELLED->value)->count(),
        ];

        $recent = Ride::query()
            ->where('user_id', $userId)
            ->where('status', '!=', RideStatusEnum::SYSTEM_CANCELLED->value)
            ->with(['driver', 'user', 'conversation'])
            ->orderByDesc('created_at')
            ->limit(6)
            ->get();

        return sendResponse(
            true,
            'User dashboard fetched successfully.',
            [
                'summary' => $summary,
                'recent_rides' => RideResource::collection($recent),
            ],
            HttpStatus::HTTP_OK
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
}
