<?php

namespace App\Support\Auth;

use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class GuestToken
{
    public static function fromRequest(Request $request): string
    {
        $token = trim((string) ($request->headers->get('X-Guest-Token') ?? $request->headers->get('X-Gust-Token') ?? ''));

        if ($token === '') {
            throw ValidationException::withMessages([
                'guest_token' => [__('api.guest_token_required')],
            ]);
        }

        return $token;
    }

    public static function hash(string $guestToken): string
    {
        return hash('sha256', $guestToken);
    }

    public static function assertMatches(Request $request, ?string $expectedHash): void
    {
        if ($expectedHash === null || $expectedHash === '') {
            throw ValidationException::withMessages([
                'guest_token' => [__('api.guest_token_invalid')],
            ]);
        }

        if (! hash_equals($expectedHash, self::hash(self::fromRequest($request)))) {
            throw ValidationException::withMessages([
                'guest_token' => [__('api.guest_token_invalid')],
            ]);
        }
    }
}
