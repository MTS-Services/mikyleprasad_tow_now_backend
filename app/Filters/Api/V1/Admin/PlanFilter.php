<?php

namespace App\Filters\Api\V1\Admin;

use Illuminate\Database\Eloquent\Builder;

class PlanFilter
{
    private function __construct(private Builder $query) {}

    public static function apply(Builder $query, $request): Builder
    {
        return (new self($query))
        ->withSearch($request->searchTerm())
        ->withFilterStatus($request->status())
        ->query;
    }

    public function withSearch(?string $term): self
    {
        if ($term === null) {
            return $this;
        }

        $this->query->where('name', 'like', "%{$term}%");
        return $this;
    }

    public function withFilterStatus(?string $status): self
    {
        if ($status === null) {
            return $this;
        }

        $this->query->where('status', $status);
        return $this;
    }
}
