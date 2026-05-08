<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\UserRole;
use App\Models\User;
use App\Support\Filters\DriverQueryFilters;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class DriverSearchService
{
    public function __construct(
        private readonly DriverQueryFilters $driverQueryFilters
    ) {}

    /**
     * @param  array{q?: ?string, status?: ?string, per_page?: int}  $filters
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        $query = User::query()
            ->where('role', UserRole::DRIVER->value)
            ->with('vehicle')
            ->orderByDesc('id');

        $this->driverQueryFilters->apply($query, $filters);

        $perPage = (int) ($filters['per_page'] ?? 10);

        return $query->paginate($perPage)->withQueryString();
    }
}
