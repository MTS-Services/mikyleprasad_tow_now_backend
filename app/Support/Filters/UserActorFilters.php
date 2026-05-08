<?php

declare(strict_types=1);

namespace App\Support\Filters;

use Illuminate\Database\Eloquent\Builder;

class UserActorFilters
{
    /**
     * @param  array{
     *   q?: ?string,
     *   status?: ?string,
     *   approval_status?: ?string,
     *   is_suspended?: bool|int|string|null,
     *   is_featured?: bool|int|string|null
     * }  $filters
     */
    public function apply(Builder $query, array $filters): Builder
    {
        return $query
            ->when(
                trim((string) ($filters['q'] ?? '')) !== '',
                fn (Builder $builder): Builder => $this->applySearch($builder, (string) $filters['q'])
            )
            ->when(
                filled($filters['status'] ?? null),
                fn (Builder $builder): Builder => $builder->where('status', strtolower((string) $filters['status']))
            )
            ->when(
                filled($filters['approval_status'] ?? null),
                fn (Builder $builder): Builder => $builder->where('approval_status', strtolower((string) $filters['approval_status']))
            )
            ->when(
                isset($filters['is_suspended']),
                fn (Builder $builder): Builder => $builder->where('is_suspended', filter_var($filters['is_suspended'], FILTER_VALIDATE_BOOLEAN))
            )
            ->when(
                isset($filters['is_featured']),
                fn (Builder $builder): Builder => $builder->where('is_featured', filter_var($filters['is_featured'], FILTER_VALIDATE_BOOLEAN))
            );
    }

    public function applySearch(Builder $query, string $rawSearch): Builder
    {
        $search = trim($rawSearch);
        if ($search === '') {
            return $query;
        }

        $like = "%{$search}%";

        return $query->where(function (Builder $builder) use ($like): void {
            $builder
                ->where('name', 'like', $like)
                ->orWhere('username', 'like', $like)
                ->orWhere('email', 'like', $like)
                ->orWhere('phone', 'like', $like)
                ->orWhere('address', 'like', $like);
        });
    }
}
