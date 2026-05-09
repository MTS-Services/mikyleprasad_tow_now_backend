<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\RideStatusEnum;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\RideResource;
use App\Models\Ride;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpFoundation\Response as HttpStatus;
use App\Services\RideLifecycleService;
use App\Support\Filters\RideQueryFilters;

class RideController extends Controller
{

    public function __construct(
        private readonly RideQueryFilters $rideQueryFilters,
        private readonly RideLifecycleService $rideLifecycleService,
    ) {}

    public function stats(): JsonResponse
    {
        try {
            $stats = $this->rideLifecycleService->getStats(request()->user(), 'admin');

            return sendResponse(
                status: true,
                message: 'Stats fetched successfully.',
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
            $rides = $this->rideLifecycleService->getRides(params: [
                'filters' => $validated,
                'customQuery' => [
                    'status' => [
                        'operator' => '!=',
                        'value' => RideStatusEnum::SYSTEM_CANCELLED->value,
                    ],
                ],
            ]);

            // $rides = $this->rideQueryFilters->apply(
            //     query: Ride::query(),
            //     filters: $validated,
            //     customQuery: [
            //         'status' => [
            //             'operator' => '!=',
            //             'value' => RideStatusEnum::SYSTEM_CANCELLED->value,
            //         ],
            //     ],
            // )->with(['user', 'driver', 'conversation'])->paginate(perPage: (int) ($validated['per_page'] ?? 15), page: (int) ($validated['page'] ?? 1), pageName: 'page')->withQueryString();

            return sendResponse(
                status: true,
                message: 'Admin rides fetched successfully.',
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
            $ride = $this->rideLifecycleService->getRide(params: [
                'value' => $id,
                'column' => 'id',
                'customQuery' => [
                    'status' => [
                        'operator' => '!=',
                        'value' => RideStatusEnum::SYSTEM_CANCELLED->value,
                    ],
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
}
