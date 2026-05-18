<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Review;
use Illuminate\Pagination\LengthAwarePaginator;

class ReviewService
{
    public function __construct(private readonly Review $review) {}

    public function create(array $data): Review
    {
        return $this->review->create($data);
    }

    /**
     * @param  array{per_page?: int}  $filters
     */
    public function paginate(array $filters = []): LengthAwarePaginator
    {
        $perPage = (int) ($filters['per_page'] ?? 15);

        return $this->review
            ->newQuery()
            ->with([
                'user',
                'ride.driver',
                'reviewReplays' => fn ($query) => $query
                    ->whereNull('parent_id')
                    ->with(['user', 'replies.user']),
            ])
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function driverReviews(int $driverId, array $filters = []): LengthAwarePaginator
    {
        $perPage = (int) ($filters['per_page'] ?? 15);

        return $this->review
            ->newQuery()
            ->with([
                'user',
                'ride.driver',
                'reviewReplays' => fn ($query) => $query
                    ->whereNull('parent_id')
                    ->with(['user', 'replies.user']),
            ])
            ->whereHas('ride', function ($query) use ($driverId): void {
                $query->where('driver_id', $driverId);
            })
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString();
    }
}
