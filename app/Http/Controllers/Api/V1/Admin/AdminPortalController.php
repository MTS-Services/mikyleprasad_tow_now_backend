<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\RideStatusEnum;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ReviewResource;
use App\Http\Resources\Api\V1\RideResource;
use App\Http\Resources\Api\V1\UserResource;
use App\Models\Ride;
use App\Models\User;
use App\Services\CustomerServce;
use App\Services\DriverService;
use App\Services\ReviewService;
use App\Support\Filters\RideQueryFilters;
use App\Support\Filters\UserActorFilters;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response as HttpStatus;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminPortalController extends Controller
{
    public function __construct(
        private readonly RideQueryFilters $rideQueryFilters,
        private readonly UserActorFilters $userActorFilters,
        private readonly DriverService $driverService,
        private readonly CustomerServce $customerServce,
        private readonly ReviewService $reviewService,
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
                        RideStatusEnum::ACTIVE->value,
                        RideStatusEnum::ARRIVED->value,
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
            'tab' => ['sometimes', 'string', 'in:pending,all,suspended,featured_drivers,rejected'],
            'q' => ['sometimes', 'string'],
            'sort' => ['sometimes', 'string', 'in:latest,oldest'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $validated['audience'] = 'admin';
        $validated['tab'] ??= 'pending';
        $validated['sort'] ??= 'latest';

        $drivers = $this->driverService->paginate($validated);

        return sendResponse(
            true,
            'Admin drivers fetched successfully.',
            UserResource::collection($drivers),
            HttpStatus::HTTP_OK
        );
    }

    public function acceptDriver(User $driver): JsonResponse
    {
        $this->driverService->acceptDriver($driver->id);

        return sendResponse(true, 'Driver accepted successfully.', null, HttpStatus::HTTP_OK);
    }

    public function rejectDriver(User $driver): JsonResponse
    {
        $this->driverService->rejectDriver($driver->id);

        return sendResponse(true, 'Driver rejected successfully.', null, HttpStatus::HTTP_OK);
    }

    public function showDriver(User $driver): JsonResponse
    {

        $driverDetails = $this->driverService->find($driver->id);

        if (! $driverDetails) {
            return sendResponse(false, 'Driver not found.', statusCode: HttpStatus::HTTP_NOT_FOUND);
        }

        return sendResponse(true, 'Driver fetched successfully.', new UserResource($driverDetails), HttpStatus::HTTP_OK);
    }

    public function downloadDriverVehicleDocument(User $driver, string $document): JsonResponse|RedirectResponse|BinaryFileResponse|StreamedResponse
    {
        $allowed = ['truck_image', 'driving_license_image', 'legal_documents'];

        if (! in_array($document, $allowed, true)) {
            return sendResponse(false, 'Invalid document type.', null, HttpStatus::HTTP_NOT_FOUND);
        }

        $driverDetails = $this->driverService->find($driver->id);

        if (! $driverDetails?->vehicle) {
            return sendResponse(false, 'Driver or vehicle not found.', null, HttpStatus::HTTP_NOT_FOUND);
        }

        $path = $driverDetails->vehicle->{$document};

        if ($path === null || $path === '') {
            return sendResponse(false, 'Document not found.', null, HttpStatus::HTTP_NOT_FOUND);
        }

        $trimmed = ltrim($path, '/');

        if (str_starts_with($trimmed, 'http://') || str_starts_with($trimmed, 'https://')) {
            return redirect()->away($path);
        }

        if (str_starts_with($trimmed, 'storage/')) {
            $trimmed = substr($trimmed, strlen('storage/'));
        }

        if (! Storage::disk('public')->exists($trimmed)) {
            return sendResponse(false, 'File not found.', null, HttpStatus::HTTP_NOT_FOUND);
        }

        $filename = $document.'-'.($driverDetails->username ?? $driverDetails->id).'-'.basename($trimmed);

        return Storage::disk('public')->download($trimmed, $filename);
    }

    public function customers(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['sometimes', 'string'],
            'status' => ['sometimes', 'string'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $customers = $this->customerServce->paginate($validated);

        return sendResponse(true, 'Admin customers fetched successfully.', UserResource::collection($customers), HttpStatus::HTTP_OK);
    }

    public function showCustomer(User $customer): JsonResponse
    {
        $customerDetails = $this->customerServce->find($customer->id);

        if (! $customerDetails) {
            return sendResponse(false, 'Customer not found.', statusCode: HttpStatus::HTTP_NOT_FOUND);
        }

        return sendResponse(true, 'Customer fetched successfully.', new UserResource($customerDetails), HttpStatus::HTTP_OK);
    }

    public function reviews(): JsonResponse
    {
        $reviews = $this->reviewService->getAll();

        return sendResponse(true, 'Admin reviews fetched successfully.', ReviewResource::collection($reviews), HttpStatus::HTTP_OK);
    }

    public function suspendDriver(User $driver): JsonResponse
    {
        $this->driverService->suspendDriver($driver->id);

        return sendResponse(true, 'Driver suspended successfully.', null, HttpStatus::HTTP_OK);
    }
    public function unsuspendDriver(User $driver): JsonResponse
    {
        $this->driverService->unsuspendDriver($driver->id);

        return sendResponse(true, 'Driver unsuspended successfully.', null, HttpStatus::HTTP_OK);
    }

    public function featuredDriver(User $driver): JsonResponse
    {
        $this->driverService->featuredDriver($driver->id);

        return sendResponse(true, 'Driver featured successfully.', null, HttpStatus::HTTP_OK);
    }
    public function unfeaturedDriver(User $driver): JsonResponse
    {
        $this->driverService->unfeaturedDriver($driver->id);

        return sendResponse(true, 'Driver unfeatured successfully.', null, HttpStatus::HTTP_OK);
    }
}
