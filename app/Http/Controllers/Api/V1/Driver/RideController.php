<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Driver;

use App\Enums\RideStatusEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Ride\AcceptRideRequest;
use App\Http\Requests\Api\V1\Ride\CancelRideRequest;
use App\Http\Requests\Api\V1\Ride\UpdateRideEtaRequest;
use App\Http\Resources\Api\V1\RideResource;
use App\Models\Ride;
use App\Services\RideLifecycleService;
use App\Support\Filters\RideQueryFilters;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as HttpStatus;
use Symfony\Component\HttpKernel\Exception\HttpException;

class RideController extends Controller
{
    public function __construct(
        private readonly RideLifecycleService $rideLifecycleService,
        private readonly RideQueryFilters $rideQueryFilters,
    ) {}

    public function stats(Request $request): JsonResponse
    {
        try {
            $stats = $this->rideLifecycleService->getStats($request->user(), 'driver');

            return sendResponse(
                status: true,
                message: 'Driver rides stats fetched successfully.',
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

    public function incoming(Request $request): JsonResponse
    {
        try {
            $rides = $this->buildDriverListQuery($request, [RideStatusEnum::PENDING->value])
                ->paginate((int) $request->integer('per_page', 15))
                ->withQueryString();

            return sendResponse(
                true,
                'Incoming rides fetched successfully.',
                RideResource::collection($rides),
                HttpStatus::HTTP_OK
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
            'tab' => ['sometimes', 'in:pending,active,completed,cancelled,expired,history'],
            'status' => ['sometimes', 'array'],
            'status.*' => ['string'],
            'q' => ['sometimes', 'string'],
            'from' => ['sometimes', 'date'],
            'to' => ['sometimes', 'date'],
            'sort' => ['sometimes', 'in:latest,oldest'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        try {
            $tab = (string) ($validated['tab'] ?? 'pending');
            $statuses = $validated['status'] ?? $this->tabStatuses($tab);
            $perPage = (int) ($validated['per_page'] ?? 15);

            $rides = $this->buildDriverListQuery($request, $statuses, $validated)
                ->paginate($perPage)
                ->withQueryString();

            return sendResponse(
                status: true,
                message: 'Driver rides fetched successfully.',
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

    public function show(Request $request, string $id): JsonResponse
    {
        try {
            $ride = $this->rideLifecycleService->getRide([
                'value' => $id,
                'column' => 'id',
                'customQuery' => [
                    'driver_id' => $request->user()->id,
                    'status' => ['operator' => '!=', 'value' => RideStatusEnum::SYSTEM_CANCELLED->value],
                ],
                'with' => ['user', 'driver', 'conversation', 'histories'],
            ]);

            return sendResponse(
                status: true,
                message: 'Ride fetched successfully.',
                data: new RideResource($ride),
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

    public function accept(AcceptRideRequest $request, Ride $ride): JsonResponse
    {
        try {
            $ride = $this->rideLifecycleService->acceptByDriver(
                $ride->load(['user', 'driver', 'conversation', 'histories']),
                $request->user(),
                (int) $request->validated('eta_minutes')
            );

            return sendResponse(true, 'Ride accepted successfully.', new RideResource($ride), HttpStatus::HTTP_OK);
        } catch (HttpException $e) {
            return sendResponse(false, $e->getMessage(), statusCode: $e->getStatusCode());
        }
    }

    public function updateEta(UpdateRideEtaRequest $request, Ride $ride): JsonResponse
    {
        try {
            $ride = $this->rideLifecycleService->updateEta(
                $ride->load(['user', 'driver', 'conversation', 'histories']),
                $request->user(),
                (int) $request->validated('eta_minutes'),
                (string) $request->validated('reason')
            );

            return sendResponse(true, 'Ride ETA updated successfully.', new RideResource($ride), HttpStatus::HTTP_OK);
        } catch (HttpException $e) {
            return sendResponse(false, $e->getMessage(), statusCode: $e->getStatusCode());
        }
    }

    public function cancel(CancelRideRequest $request, Ride $ride): JsonResponse
    {
        try {
            $ride = $this->rideLifecycleService->cancelByDriver(
                $ride->load(['user', 'driver', 'conversation', 'histories']),
                $request->user(),
                $request->validated('reason')
            );

            return sendResponse(true, 'Ride cancelled successfully.', new RideResource($ride), HttpStatus::HTTP_OK);
        } catch (HttpException $e) {
            return sendResponse(false, $e->getMessage(), statusCode: $e->getStatusCode());
        }
    }

    public function completeRequest(Request $request, Ride $ride): JsonResponse
    {
        try {
            $ride = $this->rideLifecycleService->completeByDriver(
                $ride->load(['user', 'driver', 'conversation', 'histories']),
                $request->user()
            );

            return sendResponse(true, 'Ride completed successfully.', new RideResource($ride), HttpStatus::HTTP_OK);
        } catch (HttpException $e) {
            return sendResponse(false, $e->getMessage(), statusCode: $e->getStatusCode());
        }
    }

    public function markArrived(Request $request, Ride $ride): JsonResponse
    {
        try {
            $ride = $this->rideLifecycleService->markArrived(
                $ride->load(['user', 'driver', 'conversation', 'histories']),
                $request->user()
            );

            return sendResponse(true, 'Ride marked as arrived.', new RideResource($ride), HttpStatus::HTTP_OK);
        } catch (HttpException $e) {
            return sendResponse(false, $e->getMessage(), statusCode: $e->getStatusCode());
        }
    }

    /**
     * @param  array<int, string>  $statuses
     * @param  array<string, mixed>  $filters
     */
    private function buildDriverListQuery(Request $request, array $statuses, array $filters = [])
    {
        $query = Ride::query()
            ->where('driver_id', $request->user()->id)
            ->where('status', '!=', RideStatusEnum::SYSTEM_CANCELLED->value)
            ->with(['user', 'driver', 'conversation', 'histories']);

        return $this->rideQueryFilters->apply($query, array_merge($filters, ['status' => $statuses]));
    }

    /**
     * @return array<int, string>
     */
    private function tabStatuses(string $tab): array
    {
        return match ($tab) {
            'active' => [
                RideStatusEnum::ACTIVE->value,
                RideStatusEnum::ARRIVED->value,
            ],
            'completed' => [
                RideStatusEnum::COMPLETED_USER->value,
            ],
            'cancelled' => [
                RideStatusEnum::CANCELLED_BY_DRIVER->value,
                RideStatusEnum::CANCELLED_BY_USER->value,
            ],
            'expired' => [
                RideStatusEnum::EXPIRED->value,
            ],
            'history' => [
                RideStatusEnum::COMPLETED_USER->value,
                RideStatusEnum::CANCELLED_BY_DRIVER->value,
                RideStatusEnum::CANCELLED_BY_USER->value,
                RideStatusEnum::EXPIRED->value,
            ],
            default => [RideStatusEnum::PENDING->value],
        };
    }
}
