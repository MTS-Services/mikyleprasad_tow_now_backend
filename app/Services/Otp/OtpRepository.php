<?php

namespace App\Services\Otp;

use App\Enums\OtpPurpose;
use Illuminate\Support\Facades\Cache;

class OtpRepository
{
    public function cacheKey(OtpPurpose $purpose, string $fingerprint): string
    {
        return sprintf('otp:%s:%s', $purpose->value, $fingerprint);
    }

    /**
     * @param  array{user_id: int, hash: string, guest_token_hash?: string}  $payload
     */
    public function put(OtpPurpose $purpose, string $fingerprint, array $payload, int $ttlMinutes): void
    {
        Cache::put($this->cacheKey($purpose, $fingerprint), $payload, now()->addMinutes($ttlMinutes));
    }

    /**
     * @return array{user_id: int, hash: string, guest_token_hash?: string}|null
     */
    public function get(OtpPurpose $purpose, string $fingerprint): ?array
    {
        $value = Cache::get($this->cacheKey($purpose, $fingerprint));

        return is_array($value)
            && isset($value['user_id'], $value['hash'])
            && is_int($value['user_id'])
            && is_string($value['hash'])
            ? array_filter([
                'user_id' => $value['user_id'],
                'hash' => $value['hash'],
                'guest_token_hash' => isset($value['guest_token_hash']) && is_string($value['guest_token_hash'])
                    ? $value['guest_token_hash']
                    : null,
            ], fn (mixed $item): bool => $item !== null)
            : null;
    }

    public function forget(OtpPurpose $purpose, string $fingerprint): void
    {
        Cache::forget($this->cacheKey($purpose, $fingerprint));
    }

    public static function fingerprint(string $identifierType, string $normalizedIdentifier): string
    {
        return hash('sha256', $identifierType.'|'.$normalizedIdentifier);
    }

    public static function hashCode(string $plainCode): string
    {
        return hash_hmac('sha256', $plainCode, (string) config('app.key'));
    }
}
