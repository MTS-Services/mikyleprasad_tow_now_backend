<?php

declare(strict_types=1);

namespace App\Support\Filters;

use Illuminate\Database\Eloquent\Builder;

class RideQueryFilters
{
    /**
     * @param  array{
     *   q?: ?string,
     *   from?: ?string,
     *   to?: ?string,
     *   sort?: ?string,
     *   status?: array<int, string>|string|null
     * }  $filters
     */
    public function apply(Builder $query, array $filters): Builder
    {
        $statuses = $this->normalizeStatuses($filters['status'] ?? null);
        $sort = strtolower((string) ($filters['sort'] ?? 'latest'));

        return $query
            ->when(
                trim((string) ($filters['q'] ?? '')) !== '',
                fn (Builder $builder): Builder => $this->applySearch($builder, (string) $filters['q'])
            )
            ->when(
                ! empty($statuses),
                fn (Builder $builder): Builder => $builder->whereIn('status', $statuses)
            )
            ->when(
                filled($filters['from'] ?? null),
                fn (Builder $builder): Builder => $builder->whereDate('created_at', '>=', (string) $filters['from'])
            )
            ->when(
                filled($filters['to'] ?? null),
                fn (Builder $builder): Builder => $builder->whereDate('created_at', '<=', (string) $filters['to'])
            )
            ->when(
                $sort === 'oldest',
                fn (Builder $builder): Builder => $builder->orderBy('created_at')
            )
            ->when(
                $sort !== 'oldest',
                fn (Builder $builder): Builder => $builder->orderByDesc('created_at')
            );
    }

    public function applySearch(Builder $query, string $rawSearch): Builder
    {
        $search = trim($rawSearch);
        if ($search === '') {
            return $query;
        }

        $like = "%{$search}%";

        return $query->where(function (Builder $builder) use ($like, $search): void {
            $builder
                ->where('pickup_location', 'like', $like)
                ->orWhere('dropoff_location', 'like', $like)
                ->orWhere('notes', 'like', $like)
                ->orWhere('uuid', 'like', $like)
                ->orWhere('id', (int) $search);
        });
    }

    /**
     * @param  array<int, string>|string|null  $statuses
     * @return array<int, string>
     */
    private function normalizeStatuses(array|string|null $statuses): array
    {
        if (is_string($statuses)) {
            $statuses = explode(',', $statuses);
        }

        if (! is_array($statuses)) {
            return [];
        }

        return array_values(
            array_filter(
                array_map(static fn (mixed $status): string => strtolower(trim((string) $status)), $statuses),
                static fn (string $status): bool => $status !== ''
            )
        );
    }
}
