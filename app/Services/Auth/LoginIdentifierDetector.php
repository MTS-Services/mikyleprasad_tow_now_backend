<?php

namespace App\Services\Auth;

use App\Enums\LoginIdentifierType;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginIdentifierDetector
{
    /**
     * Resolve login identifier type and normalized value. Optional explicit type is validated when provided.
     *
     * @param  list<LoginIdentifierType>  $allowed
     * @return array{0: LoginIdentifierType, 1: string}
     */
    public function resolve(?string $explicitType, string $raw, array $allowed): array
    {
        $trimmed = trim($raw);
        if ($trimmed === '') {
            throw ValidationException::withMessages([
                'identifier' => [__('validation.required', ['attribute' => 'identifier'])],
            ]);
        }

        $explicit = LoginIdentifierType::tryFrom((string) ($explicitType ?? ''));
        if ($explicit !== null) {
            if (! in_array($explicit, $allowed, true)) {
                throw ValidationException::withMessages([
                    'identifier_type' => [__('api.otp_identifier_type_not_allowed')],
                ]);
            }

            $this->assertShapeMatchesType($explicit, $trimmed);

            return [$explicit, $this->normalize($explicit, $trimmed)];
        }

        return $this->detectOrFail($trimmed, $allowed);
    }

    /**
     * Stable segment for rate limiting before FormRequest validation runs.
     * Never throws; falls back when the value cannot be classified.
     *
     * @param  list<LoginIdentifierType>  $allowed
     */
    public static function throttleSegment(string $raw, mixed $explicitType, array $allowed): string
    {
        $explicit = LoginIdentifierType::tryFrom((string) ($explicitType ?? ''));
        $trimmed = trim($raw);

        if ($explicit !== null && in_array($explicit, $allowed, true) && $trimmed !== '') {
            $normalized = match ($explicit) {
                LoginIdentifierType::Email => Str::lower($trimmed),
                LoginIdentifierType::Phone => preg_replace('/\s+/', '', $trimmed) ?? $trimmed,
                LoginIdentifierType::Username => Str::lower($trimmed),
            };

            return $explicit->value.'|'.$normalized;
        }

        $detected = self::tryDetect($trimmed, $allowed);
        if ($detected !== null) {
            return $detected[0]->value.'|'.$detected[1];
        }

        return 'unknown|'.hash('xxh128', Str::lower($trimmed));
    }

    /**
     * Resolve the login handle from the raw request for rate limiting (runs before FormRequest merge).
     *
     * @param  list<LoginIdentifierType>  $allowed
     */
    public static function rawCredentialStringFromRequest(Request $request, bool $includeFortifyUsernameField): string
    {
        $trim = static function (mixed $value): string {
            return is_string($value) ? trim($value) : '';
        };

        $fromIdentifier = $trim($request->input('identifier'));
        if ($fromIdentifier !== '') {
            return $fromIdentifier;
        }

        $auth = app(AuthLoginConfiguration::class);
        $allowed = $auth->loginIdentifierTypes();

        if (count($allowed) === 1) {
            $dedicated = match ($allowed[0]) {
                LoginIdentifierType::Email => $trim($request->input('email')),
                LoginIdentifierType::Phone => $trim($request->input('phone')),
                LoginIdentifierType::Username => $trim($request->input('username')),
            };
            if ($dedicated !== '') {
                return $dedicated;
            }
        }

        if ($includeFortifyUsernameField) {
            $usernameField = config('fortify.username', 'email');

            return $trim($request->input($usernameField));
        }

        return '';
    }

    /**
     * @param  list<LoginIdentifierType>  $allowed
     * @return array{0: LoginIdentifierType, 1: string}|null
     */
    public static function tryDetect(string $trimmed, array $allowed): ?array
    {
        if ($trimmed === '' || $allowed === []) {
            return null;
        }

        if (count($allowed) === 1) {
            $only = $allowed[0];

            return match ($only) {
                LoginIdentifierType::Email => filter_var($trimmed, FILTER_VALIDATE_EMAIL)
                    ? [LoginIdentifierType::Email, Str::lower($trimmed)]
                    : null,
                LoginIdentifierType::Phone => self::looksLikePhone($trimmed)
                    ? [LoginIdentifierType::Phone, self::normalizePhoneDigits($trimmed)]
                    : null,
                LoginIdentifierType::Username => [LoginIdentifierType::Username, Str::lower($trimmed)],
            };
        }

        if (in_array(LoginIdentifierType::Email, $allowed, true) && filter_var($trimmed, FILTER_VALIDATE_EMAIL)) {
            return [LoginIdentifierType::Email, Str::lower($trimmed)];
        }

        if (in_array(LoginIdentifierType::Phone, $allowed, true) && self::looksLikePhone($trimmed)) {
            return [LoginIdentifierType::Phone, self::normalizePhoneDigits($trimmed)];
        }

        if (in_array(LoginIdentifierType::Username, $allowed, true)) {
            return [LoginIdentifierType::Username, Str::lower($trimmed)];
        }

        return null;
    }

    /**
     * @param  list<LoginIdentifierType>  $allowed
     * @return array{0: LoginIdentifierType, 1: string}
     */
    private function detectOrFail(string $trimmed, array $allowed): array
    {
        $detected = self::tryDetect($trimmed, $allowed);
        if ($detected !== null) {
            return $detected;
        }

        if (count($allowed) === 1) {
            $only = $allowed[0];
            if ($only === LoginIdentifierType::Email) {
                throw ValidationException::withMessages([
                    'identifier' => [__('validation.email', ['attribute' => 'identifier'])],
                ]);
            }

            if ($only === LoginIdentifierType::Phone) {
                throw ValidationException::withMessages([
                    'identifier' => [__('api.otp_identifier_phone_invalid')],
                ]);
            }
        }

        throw ValidationException::withMessages([
            'identifier' => [__('api.otp_identifier_unrecognized')],
        ]);
    }

    private function assertShapeMatchesType(LoginIdentifierType $type, string $trimmed): void
    {
        if ($type === LoginIdentifierType::Email && ! filter_var($trimmed, FILTER_VALIDATE_EMAIL)) {
            throw ValidationException::withMessages([
                'identifier' => [__('validation.email', ['attribute' => 'identifier'])],
            ]);
        }

        if ($type === LoginIdentifierType::Phone && ! self::looksLikePhone($trimmed)) {
            throw ValidationException::withMessages([
                'identifier' => [__('api.otp_identifier_phone_invalid')],
            ]);
        }

        if ($type === LoginIdentifierType::Username && $trimmed === '') {
            throw ValidationException::withMessages([
                'identifier' => [__('validation.required', ['attribute' => 'identifier'])],
            ]);
        }
    }

    private function normalize(LoginIdentifierType $type, string $trimmed): string
    {
        return match ($type) {
            LoginIdentifierType::Email => Str::lower($trimmed),
            LoginIdentifierType::Phone => self::normalizePhoneDigits($trimmed),
            LoginIdentifierType::Username => Str::lower($trimmed),
        };
    }

    private static function normalizePhoneDigits(string $trimmed): string
    {
        return preg_replace('/\s+/', '', $trimmed) ?? $trimmed;
    }

    private static function looksLikePhone(string $value): bool
    {
        $compact = preg_replace('/[\s\-\(\)\.]/', '', trim($value)) ?? trim($value);

        return (bool) preg_match('/^\+?[0-9]{7,15}$/', $compact);
    }
}
