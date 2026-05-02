<?php

namespace App\Filters\Api\V1\Admin;

use App\Enums\UserStatus;
use App\Http\Requests\Api\V1\Admin\IndexUserRequest;
use Illuminate\Database\Eloquent\Builder;

class UserFilter
{
    private function __construct(private Builder $query) {}

    public static function apply(Builder $query, IndexUserRequest $request): Builder
    {
        return (new self($query))
            ->withSearch($request->searchTerm())
            ->withRole($request->filterRole())
            ->withStatus($request->filterStatus())
            ->withOrder($request->orderByColumn(), $request->orderDirection())
            ->query;
    }

    private function withSearch(?string $term): self
    {
        if ($term === null) {
            return $this;
        }

        $this->query->where(function (Builder $q) use ($term): void {
            $q->where('first_name', 'like', "%{$term}%")
                ->orWhere('last_name', 'like', "%{$term}%")
                ->orWhere('email', 'like', "%{$term}%")
                ->orWhereHas('company', function (Builder $q) use ($term): void {
                    $q->where('company_name', 'like', "%{$term}%")
                        ->orWhere('country', 'like', "%{$term}%");
                });
        });

        return $this;
    }

    private function withRole(?string $role): self
    {
        if ($role === null) {
            return $this;
        }

        $this->query->where('role', $role);

        return $this;
    }

    private function withStatus(?string $status): self
    {
        if ($status === null) {
            return $this;
        }

        $enum = UserStatus::tryFrom($status);
        if (! $enum) {
            return $this;
        }

        $this->query->where('status', $enum->value);

        return $this;
    }

    private function withOrder(string $column, string $direction): self
    {
        $this->query->orderBy($column, $direction);

        return $this;
    }
}
