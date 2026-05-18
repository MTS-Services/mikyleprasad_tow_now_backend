<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Review;
use App\Models\ReviewReplay;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class ReviewReplayService
{
    public function __construct(private readonly ReviewReplay $reviewReplay) {}

    /**
     * @param  array{body: string, parent_id?: int|null}  $data
     */
    public function createForDriver(User $driver, Review $review, array $data): ReviewReplay
    {
        $review->loadMissing('ride');

        if ($review->ride?->driver_id !== $driver->id) {
            throw ValidationException::withMessages([
                'review' => ['You may only reply to reviews on your rides.'],
            ]);
        }

        if (isset($data['parent_id'])) {
            $parentBelongsToReview = $this->reviewReplay
                ->newQuery()
                ->whereKey($data['parent_id'])
                ->where('review_id', $review->id)
                ->exists();

            if (! $parentBelongsToReview) {
                throw ValidationException::withMessages([
                    'parent_id' => ['The selected parent reply does not belong to this review.'],
                ]);
            }
        }

        $replay = $this->reviewReplay->newQuery()->create([
            'review_id' => $review->id,
            'user_id' => $driver->id,
            'parent_id' => $data['parent_id'] ?? null,
            'body' => $data['body'],
        ]);

        return $replay->load('user');
    }
}
