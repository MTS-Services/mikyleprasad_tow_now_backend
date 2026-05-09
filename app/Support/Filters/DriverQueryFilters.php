<?php

declare(strict_types=1);

namespace App\Support\Filters;

use App\Enums\AccountStatus;
use Illuminate\Database\Eloquent\Builder;

class DriverQueryFilters
{
    /**
     * @param  array{q?: ?string, status?: ?string}  $filters
     */
    public function apply(Builder $query, array $filters): Builder
    {
        $status = strtolower((string) ($filters['status'] ?? ''));

        return $query
            ->when(
                trim((string) ($filters['q'] ?? '')) !== '',
                fn (Builder $builder): Builder => $this->applySearch($builder, (string) $filters['q'])
            )
            ->when(
                $status === 'online',
                fn (Builder $builder): Builder => $builder
                    ->where('status', AccountStatus::ACTIVE->value)
            )
            ->when(
                $status === 'offline',
                fn (Builder $builder): Builder => $builder->where('status', AccountStatus::INACTIVE->value)
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
                ->where('username', 'like', $like)
                ->orWhere('name', 'like', $like)
                ->orWhere('address', 'like', $like)
                ->orWhere('bio', 'like', $like)
                ->orWhere('phone', 'like', $like)
                ->orWhereHas('vehicle', function (Builder $vehicleQuery) use ($like): void {
                    $vehicleQuery
                        ->where('name', 'like', $like)
                        ->orWhere('brand', 'like', $like)
                        ->orWhere('model', 'like', $like)
                        ->orWhere('license_plate', 'like', $like);
                });
        });
    }
}
