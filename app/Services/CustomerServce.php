<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\User;
use App\Support\Filters\UserActorFilters;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class CustomerServce
{
    public function __construct(
        private readonly UserActorFilters $userActorFilters
    ) {}

    /**
     * @param  array{q?: ?string, status?: ?string, featured?: string, per_page?: int}  $filters
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        $query = User::query()
            ->where('role', UserRole::USER->value)
            ->orderByDesc('id');

        $this->userActorFilters->apply($query, $filters);

        $perPage = (int) ($filters['per_page'] ?? 15);

        return $query->paginate($perPage)->withQueryString();
    }

    public function find(int $id): ?User
    {
        return User::query()
            ->whereKey($id)
            ->where('role', UserRole::USER->value)
            ->first();
    }

    public function getCustomerProfile(): ?User
    {
        return User::query()
            ->whereKey(auth()->id())
            ->where('role', UserRole::USER->value)
            ->first();
    }
    
    public function updateCustomerProfile(array $data): ?User
    {
        $customer = User::query()
            ->whereKey(auth()->id())
            ->where('role', UserRole::USER->value)
            ->first();
        
        if ($customer) {
            $customer->update($data);
            return $customer;
        }
        
        return null;
    }
}
