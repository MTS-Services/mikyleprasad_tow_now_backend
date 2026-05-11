<?php

namespace App\Services;

use App\Models\Review;

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

}
