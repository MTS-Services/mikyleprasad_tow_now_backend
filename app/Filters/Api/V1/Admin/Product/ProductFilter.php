<?php

namespace App\Filters\Api\V1\Admin\Product;

use Illuminate\Database\Eloquent\Builder;

class ProductFilter
{
    /**
     * Create a new class instance.
     */
    public function __construct(private Builder $query)
    {
        //
    }

    public static function apply(Builder $query, $request): Builder
    {
        return (new self($query))
            ->withSearch($request->filterSearch())
            ->withApprovalFilter($request->filterStatus())
            ->query;
    }

    private function withSearch($term): self
    {
        if ($term === null) {
            return $this;
        }

        $this->query->where('name', 'like', '%'.$term.'%');

        return $this;
    }

    private function withApprovalFilter(?bool $isApproved): self
    {
        if ($isApproved === null) {
            return $this;
        }

        $this->query->where('is_approved', $isApproved);

        return $this;
    }
}
