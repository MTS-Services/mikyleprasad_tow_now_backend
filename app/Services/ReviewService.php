<?php

namespace App\Services;

use App\Models\Review;
use Illuminate\Pagination\LengthAwarePaginator;

class ReviewService
{
    /**
     * Create a new class instance.
     */
    public function __construct(private readonly Review $review)
    {
    }
    
    public function create(array $data): Review
    {
        return $this->review->create($data);
    }

    public function getAll(){
        return $this->review->with(['user', 'ride.driver'])->get();
    }

    public function paginate(array $filters): LengthAwarePaginator
    {
        return $this->review->paginate($filters['per_page'] ?? 15);
    }

    public function driverReviews($driverId): LengthAwarePaginator
    {
        return $this->review->whereHas('ride', function ($query) use ($driverId) {
            $query->where('driver_id', $driverId);
        })->paginate(15);
    }

}
