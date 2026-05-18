<?php

namespace App\Http\Controllers\Api\V1\Driver;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ReviewReplayResource;
use App\Http\Resources\Api\V1\ReviewResource;
use App\Http\Resources\Api\V1\UserResource;
use App\Models\Review;
use App\Services\ReviewReplayService;
use App\Services\ReviewService;
use App\Services\UserServce;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response as HttpStatus;

class DriverController extends Controller
{
    public function __construct(
        private readonly UserServce $userServce,
        private readonly ReviewService $reviewService,
        private readonly ReviewReplayService $reviewReplayService,
    ) {}

    public function update(Request $request)
    {
        $data = $this->userServce->updateProfile($request, $request->all());

        if (! $data) {
            return sendResponse(false, 'User profile not found.', null, HttpStatus::HTTP_NOT_FOUND);
        }

        return sendResponse(true, 'Data updated successfully.', new UserResource($data['user']), HttpStatus::HTTP_OK);
    }

    public function updateVehicle(Request $request)
    {
        Log::info($request->all());
        $data = $this->userServce->updateVehicle($request, $request->all());

        if (! $data) {
            return sendResponse(
                false,
                'Vehicle profile not found.',
                null,
                HttpStatus::HTTP_NOT_FOUND
            );
        }

        return sendResponse(
            true,
            'Data updated successfully.',
            new UserResource($data['user']),
            HttpStatus::HTTP_OK
        );
    }

    public function reviews(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ]);

        $data = $this->reviewService->driverReviews((int) auth()->id(), $filters);

        return sendResponse(true, 'Reviews retrieved successfully.', ReviewResource::collection($data), HttpStatus::HTTP_OK);
    }

    public function storeReviewReplay(Request $request, Review $review): JsonResponse
    {
        $validated = $request->validate([
            'body' => ['required', 'string', 'max:2000'],
            'parent_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('review_replays', 'id')->where(fn ($query) => $query->where('review_id', $review->id)),
            ],
        ]);

        $replay = $this->reviewReplayService->createForDriver($request->user(), $review, $validated);

        return sendResponse(
            true,
            'Review reply created successfully.',
            new ReviewReplayResource($replay),
            HttpStatus::HTTP_CREATED
        );
    }
}
