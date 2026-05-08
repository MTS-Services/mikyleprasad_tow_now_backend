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
use App\Support\Filters\RideQueryFilters;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as HttpStatus;

class RideLifecycleController extends Controller
{
    public function __construct(
        private readonly RideLifecycleService $rideLifecycleService,
        private readonly RideQueryFilters $rideQueryFilters,
    ) {}

    public function incoming(Request $request): JsonResponse
    {
        $rides = $this->buildDriverListQuery($request, [RideStatusEnum::REQUESTED->value])
            ->paginate((int) $request->integer('per_page', 15))
            ->withQueryString();

        return sendResponse(
            true,
            'Incoming rides fetched successfully.',
            RideResource::collection($rides),
            HttpStatus::HTTP_OK
        );
    }

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'tab' => ['sometimes', 'in:pending,active,history'],
            'status' => ['sometimes', 'array'],
            'status.*' => ['string'],
            'q' => ['sometimes', 'string'],
            'from' => ['sometimes', 'date'],
            'to' => ['sometimes', 'date'],
            'sort' => ['sometimes', 'in:latest,oldest'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $tab = (string) ($validated['tab'] ?? 'pending');
        $statuses = $validated['status'] ?? $this->tabStatuses($tab);
        $perPage = (int) ($validated['per_page'] ?? 15);

        $rides = $this->buildDriverListQuery($request, $statuses, $validated)
            ->paginate($perPage)
            ->withQueryString();

        return sendResponse(
            true,
            'Driver rides fetched successfully.',
            RideResource::collection($rides),
            HttpStatus::HTTP_OK
        );
    }

    public function dashboard(Request $request): JsonResponse
    {
        $driverId = $request->user()->id;

        $summary = [
            'pending' => Ride::query()->where('driver_id', $driverId)->where('status', RideStatusEnum::REQUESTED->value)->count(),
            'active' => Ride::query()->where('driver_id', $driverId)->whereIn('status', $this->tabStatuses('active'))->count(),
            'completed' => Ride::query()->where('driver_id', $driverId)->whereIn('status', [
                RideStatusEnum::COMPLETED_USER->value,
                RideStatusEnum::COMPLETED_DRIVER_PENDING_USER->value,
            ])->count(),
            'cancelled_or_expired' => Ride::query()->where('driver_id', $driverId)->whereIn('status', [
                RideStatusEnum::CANCELLED_BY_DRIVER->value,
                RideStatusEnum::CANCELLED_BY_USER->value,
                RideStatusEnum::EXPIRED->value,
            ])->count(),
            'total' => Ride::query()->where('driver_id', $driverId)->where('status', '!=', RideStatusEnum::SYSTEM_CANCELLED->value)->count(),
        ];

        $recent = Ride::query()
            ->where('driver_id', $driverId)
            ->where('status', '!=', RideStatusEnum::SYSTEM_CANCELLED->value)
            ->with(['user', 'driver', 'conversation'])
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();

        return sendResponse(
            true,
            'Driver dashboard fetched successfully.',
            [
                'summary' => $summary,
                'recent_rides' => RideResource::collection($recent),
            ],
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

    /**
     * @param  array<int, string>  $statuses
     * @param  array<string, mixed>  $filters
     */
    private function buildDriverListQuery(Request $request, array $statuses, array $filters = [])
    {
        $query = Ride::query()
            ->where('driver_id', $request->user()->id)
            ->where('status', '!=', RideStatusEnum::SYSTEM_CANCELLED->value)
            ->with(['user', 'driver', 'conversation']);

        return $this->rideQueryFilters->apply($query, array_merge($filters, ['status' => $statuses]));
    }

    /**
     * @return array<int, string>
     */
    private function tabStatuses(string $tab): array
    {
        return match ($tab) {
            'active' => [
                RideStatusEnum::ACCEPTED->value,
                RideStatusEnum::ARRIVED->value,
                RideStatusEnum::PICKED_UP->value,
            ],
            'history' => [
                RideStatusEnum::COMPLETED_USER->value,
                RideStatusEnum::COMPLETED_DRIVER_PENDING_USER->value,
                RideStatusEnum::CANCELLED_BY_DRIVER->value,
                RideStatusEnum::CANCELLED_BY_USER->value,
                RideStatusEnum::EXPIRED->value,
            ],
            default => [RideStatusEnum::REQUESTED->value],
        };
    }
}
