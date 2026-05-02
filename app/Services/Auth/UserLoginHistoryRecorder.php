<?php

namespace App\Services\Auth;

use App\Models\User;
use App\Models\UserLoginHistory;
use Illuminate\Http\Request;

class UserLoginHistoryRecorder
{
    public function record(User $user, Request $request): void
    {
        UserLoginHistory::query()->create([
            'user_id' => $user->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }
}
