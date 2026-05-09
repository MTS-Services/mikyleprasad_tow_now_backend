<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ApprovalStatus;
use App\Enums\UserRole;
use App\Models\User;
use App\Support\Filters\DriverQueryFilters;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class DriverService
{
    public function __construct(
        private readonly DriverQueryFilters $driverQueryFilters
    ) {}

    /**
     * @param  array{q?: ?string, status?: ?string, featured?: string, per_page?: int}  $filters
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        $query = User::query()
            ->where('role', UserRole::DRIVER->value)
            ->where('approval_status', ApprovalStatus::APPROVED->value)
            ->where('is_suspended', false)
            ->with('vehicle')
            ->orderByDesc('id');

        $this->driverQueryFilters->apply($query, $filters);

        $featured = filter_var((string) ($filters['featured'] ?? ''), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        if ($featured === true) {
            $query->where('is_featured', true);
        }

        $perPage = (int) ($filters['per_page'] ?? 10);

        return $query->paginate($perPage)->withQueryString();
    }
}
