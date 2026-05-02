<?php

namespace App\Filters\Api\V1\Admin;

use Illuminate\Database\Eloquent\Builder;

class SubCategoryFilter
{
    /**
     * Create a new class instance.
     */
    public function __construct(private Builder $query)
    {
        
    }

    public static function apply($query, $request)
    {
        return (new self($query))
        ->withSearch($request->searchTerm())
        ->withOrder($request->orderByColumn(), $request->orderDirection())
        ->query;
    }

    public function withSearch(?string $term): self
    {
        if ($term === null) {
            return $this;
        }

        $this->query->where(
            function (Builder $q) use ($term): void {
                $q->where('name', 'like', "%{$term}%")
                  ->orWhereHas('category', function (Builder $q) use ($term): void {
                      $q->where('name', 'like', "%{$term}%");
                  });
            }
        );

        return $this;
    }

    public function withOrder(string $column, string $direction): self
    {
        $this->query->orderBy($column, $direction);

        return $this;
    }

   
}
