<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\RideStatusEnum;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\RideResource;
use App\Http\Resources\Api\V1\UserResource;
use App\Models\Ride;
use App\Models\User;
use App\Support\Filters\RideQueryFilters;
use App\Support\Filters\UserActorFilters;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as HttpStatus;

class AdminPortalController extends Controller
{
    public function __construct(
        private readonly RideQueryFilters $rideQueryFilters,
        private readonly UserActorFilters $userActorFilters,
    ) {}

    public function dashboard(): JsonResponse
    {
        $recentRides = Ride::query()
            ->where('status', '!=', RideStatusEnum::SYSTEM_CANCELLED->value)
            ->with(['user', 'driver', 'conversation'])
            ->orderByDesc('created_at')
            ->limit(8)
            ->get();

        return sendResponse(
            true,
            'Admin dashboard fetched successfully.',
            [
                'summary' => [
                    'total_drivers' => User::query()->where('role', UserRole::DRIVER->value)->count(),
                    'active_drivers' => User::query()->where('role', UserRole::DRIVER->value)->where('status', 'active')->count(),
                    'total_customers' => User::query()->where('role', UserRole::USER->value)->count(),
                    'total_rides' => Ride::query()->where('status', '!=', RideStatusEnum::SYSTEM_CANCELLED->value)->count(),
                    'active_rides' => Ride::query()->whereIn('status', [
                        RideStatusEnum::PENDING->value,
                        RideStatusEnum::ACCEPTED->value,
                        RideStatusEnum::ARRIVED->value,
                        RideStatusEnum::PICKED_UP->value,
                        RideStatusEnum::COMPLETED_DRIVER_PENDING_USER->value,
                    ])->count(),
                ],
                'recent_rides' => RideResource::collection($recentRides),
            ],
            HttpStatus::HTTP_OK
        );
    }

    public function rides(Request $request): JsonResponse
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

        $rides = $this->rideQueryFilters
            ->apply(
                Ride::query()
                    ->where('status', '!=', RideStatusEnum::SYSTEM_CANCELLED->value)
                    ->with(['user', 'driver', 'conversation']),
                $validated
            )
            ->paginate((int) ($validated['per_page'] ?? 15))
            ->withQueryString();

        return sendResponse(true, 'Admin rides fetched successfully.', RideResource::collection($rides), HttpStatus::HTTP_OK);
    }

    public function showRide(Ride $ride): JsonResponse
    {
        if ($ride->status === RideStatusEnum::SYSTEM_CANCELLED) {
            return sendResponse(false, 'Ride not found.', null, HttpStatus::HTTP_NOT_FOUND);
        }

        $ride->load(['user', 'driver', 'conversation', 'histories']);

        return sendResponse(true, 'Ride fetched successfully.', new RideResource($ride), HttpStatus::HTTP_OK);
    }

    public function drivers(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['sometimes', 'string'],
            'status' => ['sometimes', 'string'],
            'approval_status' => ['sometimes', 'string'],
            'is_suspended' => ['sometimes', 'boolean'],
            'is_featured' => ['sometimes', 'boolean'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $drivers = $this->userActorFilters
            ->apply(
                User::query()->where('role', UserRole::DRIVER->value),
                $validated
            )
            ->orderByDesc('created_at')
            ->paginate((int) ($validated['per_page'] ?? 15))
            ->withQueryString();

        return sendResponse(true, 'Admin drivers fetched successfully.', UserResource::collection($drivers), HttpStatus::HTTP_OK);
    }

    public function customers(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['sometimes', 'string'],
            'status' => ['sometimes', 'string'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $customers = $this->userActorFilters
            ->apply(
                User::query()->where('role', UserRole::USER->value),
                $validated
            )
            ->orderByDesc('created_at')
            ->paginate((int) ($validated['per_page'] ?? 15))
            ->withQueryString();

        return sendResponse(true, 'Admin customers fetched successfully.', UserResource::collection($customers), HttpStatus::HTTP_OK);
    }
}
