<?php

namespace App\Actions\Api\V1\Auth;

use App\Models\User;
use Laravel\Passport\RefreshToken;
use Laravel\Passport\Token;

class RevokePassportTokensAction
{
    public function handle(User $user): void
    {
        $tokenIds = $user->tokens()
            ->where('revoked', false)
            ->pluck('id')
            ->all();

        if (count($tokenIds) === 0) {
            return;
        }

        Token::query()
            ->whereIn('id', $tokenIds)
            ->update(['revoked' => true]);

        RefreshToken::query()
            ->whereIn('access_token_id', $tokenIds)
            ->update(['revoked' => true]);
    }
}
