<?php

namespace App\Services;

use App\Models\User;

class DriverServce
{
    private User $user;

    /**
     * Create a new class instance.
     */
    public function __construct(User $user)
    {
        $this->user = $user;
    }

    public function getAll($filter = [])
    {
        return $this->user->where('role', 'driver')->filter($filter)->get();
    }
}
