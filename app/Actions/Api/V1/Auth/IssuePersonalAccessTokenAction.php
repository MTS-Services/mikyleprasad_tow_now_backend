<?php

namespace App\Actions\Api\V1\Auth;

use App\Models\User;

class IssuePersonalAccessTokenAction
{
    public function handle(User $user, ?string $deviceName = null): string
    {
        $resolvedDeviceName = trim((string) $deviceName);
        $resolvedDeviceName = $resolvedDeviceName !== '' ? $resolvedDeviceName : 'api-token';

        return $user->createToken($resolvedDeviceName)->accessToken;
    }
}
