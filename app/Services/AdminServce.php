<?php

namespace App\Services;

use App\Models\User;
use App\Enums\UserRole;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AdminServce
{
    /**
     * Create a new class instance.
     */
    public function __construct()
    {
    }
    
    public function getAdminProfile(Request $request): ?User
    {
        $userId = $request->user()->id;
        return User::query()
            ->whereKey($userId)
            ->where('role', UserRole::ADMIN->value)
            ->with('vehicle')
            ->first();
    }

    public function updateAdminProfile(Request $request, array $data): ?User
    {
        Validator::make($data, [
            'name'    => ['sometimes', 'string', 'max:255'],
            'phone'   => ['sometimes', 'string', 'max:20'],
            'address' => ['sometimes', 'string', 'max:500'],
        ])->validate();

        $admin = $this->getAdminProfile($request);

        if (! $admin) {
            return null;
        }

        $admin->update($data);

        return $admin->fresh();
    }
}

