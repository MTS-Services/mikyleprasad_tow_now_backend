<?php

namespace App\Http\Controllers\Api\V1\User;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ReviewResource;
use App\Models\Review;
use Illuminate\Http\Request;
use App\Services\ReviewService;
use Symfony\Component\HttpFoundation\Response as HttpStatus;

class ReviewController extends Controller
{
    public function __construct(
        private readonly ReviewService $reviewService
    ) {}

    public function store(Request $request, $ride_id)
    {
        $validated = $request->validate([
            'rating' => 'required|integer|min:1|max:5',
            'review' => 'nullable|string|max:500',
        ]);

        $userId = auth()->id();

        $alreadyReviewed = Review::where('ride_id', $ride_id)
            ->where('user_id', $userId)
            ->exists();

        if ($alreadyReviewed) {
            return sendResponse(status: false, message: 'You have already reviewed this ride.', statusCode: 422);
        }

        $review = $this->reviewService->create([
            'user_id' => $userId,
            'ride_id' => $ride_id,
            'rating'  => $validated['rating'],
            'body'    => $validated['review'] ?? '',
        ]);

        return sendResponse(status: true, message: __('api.review_created'), data: $review, statusCode: 201);
    }

    public function reviews(){
        $reviews = $this->reviewService->getAll();
        return sendResponse(true, 'Reviews fetched successfully.', ReviewResource::collection($reviews), HttpStatus::HTTP_OK);
    }
}
