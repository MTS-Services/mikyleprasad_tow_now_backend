<?php

namespace App\Http\Controllers\Api\V1\User;

use App\Enums\RideStatusEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Ride\CancelRideRequest;
use App\Http\Requests\Api\V1\Ride\OfflineSyncRidesRequest;
use App\Http\Requests\Api\V1\Ride\StoreRideRequest;
use App\Http\Resources\Api\V1\RideResource;
use App\Http\Resources\Api\V1\RideTrackResource;
use App\Models\Ride;
use App\Services\RideLifecycleService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as HttpStatus;
use Symfony\Component\HttpKernel\Exception\HttpException;

class RideController extends Controller
{
    public function __construct(
        private readonly RideLifecycleService $rideLifecycleService
    ) {}

    public function stats(Request $request): JsonResponse
    {
        try {
            $stats = $this->rideLifecycleService->getStats($request->user());

            return sendResponse(
                status: true,
                message: 'Ride stats fetched successfully.',
                data: $stats,
                statusCode: HttpStatus::HTTP_OK
            );
        } catch (HttpException $e) {
            return sendResponse(
                status: false,
                message: $e->getMessage(),
                statusCode: $e->getStatusCode(),
                data: null
            );
        }
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
            'page' => ['sometimes', 'integer', 'min:1'],
        ]);

        try {

            // $rides = $this->rideLifecycleService->getRides($request->user(), $validated);

            $rides = $this->rideLifecycleService->getRides(params: [
                'filters' => $validated,
                'customQuery' => [
                    'user_id' => $request->user()->id,
                    'status' => ['operator' => '!=', 'value' => RideStatusEnum::SYSTEM_CANCELLED->value],
                ],
            ]);

            return sendResponse(
                status: true,
                message: 'Ride history fetched successfully.',
                data: RideResource::collection($rides),
                statusCode: HttpStatus::HTTP_OK
            );
        } catch (HttpException $e) {
            return sendResponse(
                status: false,
                message: $e->getMessage(),
                statusCode: $e->getStatusCode(),
                data: null
            );
        }
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

    public function track(Request $request, string $ride): JsonResponse
    {
        try {
            $model = Ride::query()
                ->whereIdOrUuid($ride)
                ->where('user_id', $request->user()->id)
                ->with(['driver' => function ($query): void {
                    $query->select(['id', 'name', 'phone', 'current_lat', 'current_lng', 'location_updated_at']);
                }])
                ->select(['id', 'uuid', 'status', 'updated_at', 'driver_id', 'user_id'])
                ->firstOrFail();

            return response()
                ->json([
                    'success' => true,
                    'message' => 'Ride track fetched successfully.',
                    'data' => (new RideTrackResource($model))->resolve($request),
                ], HttpStatus::HTTP_OK)
                ->withHeaders([
                    'Cache-Control' => 'no-cache, must-revalidate',
                ]);
        } catch (ModelNotFoundException $e) {
            return sendResponse(
                status: false,
                message: 'Ride not found.',
                data: null,
                statusCode: HttpStatus::HTTP_NOT_FOUND
            );
        }
    }

    public function offlineSync(OfflineSyncRidesRequest $request): JsonResponse
    {
        $results = [];

        foreach ($request->validated()['rides'] as $payload) {
            $row = array_merge($payload, ['synced_from_offline' => true]);

            try {
                $ride = $this->rideLifecycleService->createRequest($request->user(), $row);
                $results[] = [
                    'offline_temp_id' => $payload['offline_temp_id'],
                    'success' => true,
                    'ride' => (new RideResource($ride))->resolve($request),
                ];
            } catch (\Throwable $e) {
                $results[] = [
                    'offline_temp_id' => $payload['offline_temp_id'],
                    'success' => false,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return sendResponse(
            status: true,
            message: 'Offline sync processed.',
            data: ['results' => $results],
            statusCode: HttpStatus::HTTP_OK
        );
    }

    public function active(Request $request): JsonResponse
    {
        $ride = Ride::query()
            ->where('user_id', $request->user()->id)
            ->whereIn('status', RideStatusEnum::inProgressRideStatuses())
            ->with(['driver', 'user', 'conversation', 'histories'])
            ->latest('id')
            ->first();

        return sendResponse(
            status: true,
            message: 'Active ride fetched successfully.',
            data: $ride ? new RideResource($ride) : null,
            statusCode: HttpStatus::HTTP_OK
        );
    }

    public function show(Request $request, string $ride): JsonResponse
    {
        try {
            $rideModel = $this->rideLifecycleService->getRide([
                'value' => $ride,
                'column' => 'id',
                'filters' => ['user_id' => $request->user()->id],
                'customQuery' => [
                    'status' => ['operator' => '!=', 'value' => RideStatusEnum::SYSTEM_CANCELLED->value],
                ],
                'with' => ['driver', 'user', 'conversation', 'histories', 'review.user'],
            ]);

            return sendResponse(
                status: true,
                message: 'Ride details fetched successfully.',
                data: new RideResource($rideModel),
                statusCode: HttpStatus::HTTP_OK
            );
        } catch (HttpException $e) {
            return sendResponse(
                status: false,
                message: $e->getMessage(),
                statusCode: $e->getStatusCode(),
                data: null
            );
        }
    }

    public function cancel(CancelRideRequest $request, Ride $ride): JsonResponse
    {
        try {
            $ride = $this->rideLifecycleService->cancelByUser(
                $ride->load(['user', 'driver', 'conversation', 'histories']),
                $request->user(),
                $request->validated('reason')
            );

            return sendResponse(
                status: true,
                message: 'Ride cancelled successfully.',
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

    public function complete(Request $request, Ride $ride): JsonResponse
    {
        try {
            $ride = $this->rideLifecycleService->completeByUser($ride->load(['user', 'driver', 'conversation', 'histories']), $request->user());

            return sendResponse(
                status: true,
                message: 'Ride completed successfully.',
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

    public function markArrived(Request $request, Ride $ride): JsonResponse
    {
        try {
            $ride = $this->rideLifecycleService->markArrived(
                $ride->load(['user', 'driver', 'conversation', 'histories']),
                $request->user()
            );

            return sendResponse(
                status: true,
                message: 'Ride marked as arrived.',
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
