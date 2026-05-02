<?php 

namespace App\Filters\Api\V1\Admin;

use Illuminate\Database\Eloquent\Builder;

class FaqCategoryFilter
{
    private function __construct(private Builder $query) {}

    public static function apply(Builder $query, $request): Builder
    {
        return (new self($query))
            ->withSearch($request->searchTerm())
            ->withOrder($request->orderBy(), $request->orderDirection())
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

    private function withOrder(?string $column, ?string $direction): self
    {
        if ($column === null) {
            return $this;
        }

        if ($column === 'sort') {
            // Put sort=0 items at the bottom, others in ascending order
            $this->query->orderByRaw('CASE WHEN sort = 0 THEN 1 ELSE 0 END, sort ASC');
        } else {
            $this->query->orderBy($column, $direction ?? 'asc');
        }

        return $this;
    }
}