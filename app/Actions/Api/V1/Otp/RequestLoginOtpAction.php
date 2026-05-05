<?php

namespace App\Actions\Api\V1\Otp;

use App\Enums\ApiErrorCode;
use App\Enums\LoginIdentifierType;
use App\Enums\OtpDeliveryPreference;
use App\Enums\OtpPurpose;
use App\Enums\UserRole;
use App\Models\User;
use App\Notifications\Auth\OtpCodeNotification;
use App\Services\Auth\AuthLoginConfiguration;
use App\Services\Auth\LoginIdentifierDetector;
use App\Services\Otp\OtpRepository;
use App\Support\Auth\GuestToken;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response as HttpStatus;

class RequestLoginOtpAction
{
    public function __construct(
        protected AuthLoginConfiguration $authLogin,
        protected OtpRepository $otpRepository,
        protected LoginIdentifierDetector $loginIdentifierDetector,
    ) {}

    public function handle(Request $request): JsonResponse
    {
        $identifiers = $this->authLogin->loginIdentifierTypes();
        $explicitType = $request->string('identifier_type')->toString() ?: null;
        $rawIdentifier = $request->string('identifier')->toString();

        [$type, $normalized] = $this->loginIdentifierDetector->resolve(
            $explicitType,
            $rawIdentifier,
            $identifiers
        );

        $request->merge([
            'identifier' => $normalized,
            'identifier_type' => $type->value,
        ]);

        $guestTokenHash = GuestToken::hash(GuestToken::fromRequest($request));
        $fingerprint = $this->guestScopedFingerprint($type, $normalized, $guestTokenHash);

        $this->ensureResendCooldownAllowsSend($fingerprint);

        $name = $request->string('name')->toString() ?: null;
        $supplementEmail = $request->string('email')->toString() ?: null;
        $supplementPhone = $request->string('phone')->toString() ?: null;

        $user = $this->findUser($type, $normalized);

        if ($user === null) {
            return sendResponse(
                status: false,
                message: __('api.no_account_exists_for_this_sign_in'),
                data: null,
                statusCode: HttpStatus::HTTP_UNAUTHORIZED
            );
        }

        $outbound = $this->resolveOutboundChannel($request, $user, $type, $normalized, $supplementEmail, $supplementPhone);

        if ($outbound === 'phone') {
            throw new HttpResponseException(
                sendResponse(
                    status: false,
                    message: __('api.sms_otp_not_available'),
                    data: null,
                    statusCode: HttpStatus::HTTP_SERVICE_UNAVAILABLE,
                    additional: ['code' => ApiErrorCode::SmsOtpNotAvailable->value]
                )
            );
        }

        if ($user === null) {
            if (! $this->authLogin->otpAllowRegistrationOnLogin()) {
                throw ValidationException::withMessages([
                    'identifier' => [__('api.otp_login_registration_disabled')],
                ]);
            }

            $user = $this->createUser(
                type: $type,
                normalized: $normalized,
                name: $name,
                supplementEmail: $supplementEmail,
                supplementPhone: $supplementPhone
            );
        }

        $emailTarget = $user->email;
        if ($emailTarget === null || $emailTarget === '') {
            throw ValidationException::withMessages([
                'email' => [__('api.otp_email_required_for_delivery')],
            ]);
        }

        $length = $this->authLogin->otpCodeLength();
        $min = 10 ** ($length - 1);
        $max = (10 ** $length) - 1;
        $plainCode = (string) random_int((int) $min, (int) $max);

        $this->otpRepository->put(
            OtpPurpose::Login,
            $fingerprint,
            [
                'user_id' => $user->id,
                'hash' => OtpRepository::hashCode($plainCode),
                'guest_token_hash' => $guestTokenHash,
            ],
            $this->authLogin->otpTtlMinutes()
        );

        $user->notify(new OtpCodeNotification($plainCode, OtpPurpose::Login));

        $this->recordResendCooldown($fingerprint);

        return sendResponse(
            status: true,
            message: __('api.otp_sent_to_email'),
            data: [
                'expires_in_minutes' => $this->authLogin->otpTtlMinutes(),
            ],
            statusCode: HttpStatus::HTTP_OK
        );
    }

    private function findUser(LoginIdentifierType $type, string $normalized): ?User
    {
        return match ($type) {
            LoginIdentifierType::Email => User::query()->where('email', $normalized)->first(),
            LoginIdentifierType::Phone => User::query()->where('phone', $normalized)->first(),
            LoginIdentifierType::Username => User::query()->where('username', $normalized)->first(),
        };
    }

    /**
     * @param  ?non-empty-string  $supplementEmail
     * @param  ?non-empty-string  $supplementPhone
     */
    private function createUser(
        LoginIdentifierType $type,
        string $normalized,
        ?string $name,
        ?string $supplementEmail,
        ?string $supplementPhone
    ): User {
        $attributes = [
            'name' => $name ?? null,
            'password' => null,
            'role' => UserRole::USER,
        ];

        return match ($type) {
            LoginIdentifierType::Email => User::query()->create(array_merge($attributes, [
                'email' => $normalized,
                'phone' => $supplementPhone,
            ])),
            LoginIdentifierType::Phone => User::query()->create(array_merge($attributes, [
                'phone' => $normalized,
                'email' => $supplementEmail,
            ])),
            LoginIdentifierType::Username => $this->createUserWithUsername(
                $attributes,
                $normalized,
                $supplementEmail,
                $supplementPhone
            ),
        };
    }

    /**
     * @param  ?non-empty-string  $supplementEmail
     * @param  ?non-empty-string  $supplementPhone
     */
    private function createUserWithUsername(
        array $attributes,
        string $normalized,
        ?string $supplementEmail,
        ?string $supplementPhone
    ): User {
        if ($supplementEmail === null && $supplementPhone === null) {
            throw ValidationException::withMessages([
                'email' => [__('api.otp_contact_required_for_username')],
            ]);
        }

        return User::query()->create(array_merge($attributes, [
            'username' => $normalized,
            'email' => $supplementEmail,
            'phone' => $supplementPhone,
        ]));
    }

    /**
     * @param  ?non-empty-string  $supplementEmail
     * @param  ?non-empty-string  $supplementPhone
     * @return 'email'|'phone'
     */
    private function resolveOutboundChannel(
        Request $request,
        ?User $user,
        LoginIdentifierType $loginType,
        string $normalized,
        ?string $supplementEmail,
        ?string $supplementPhone
    ): string {
        $identifiers = $this->authLogin->loginIdentifierTypes();
        $hasEmailId = in_array(LoginIdentifierType::Email, $identifiers, true);
        $hasPhoneId = in_array(LoginIdentifierType::Phone, $identifiers, true);

        if ($hasEmailId && ! $hasPhoneId) {
            return 'email';
        }

        if ($hasPhoneId && ! $hasEmailId) {
            return 'phone';
        }

        $preference = $this->authLogin->otpDeliveryPreference();

        if ($preference === OtpDeliveryPreference::UserChoice) {
            $choice = $request->string('delivery')->toString();
            if ($choice !== 'email' && $choice !== 'phone') {
                throw ValidationException::withMessages([
                    'delivery' => [__('api.otp_delivery_required')],
                ]);
            }

            return $choice;
        }

        if ($preference === OtpDeliveryPreference::Phone) {
            return 'phone';
        }

        $emailAddr = $user?->email ?? ($loginType === LoginIdentifierType::Email ? $normalized : $supplementEmail);
        $phoneNum = $user?->phone ?? ($loginType === LoginIdentifierType::Phone ? $normalized : $supplementPhone);

        if ($emailAddr) {
            return 'email';
        }

        if ($phoneNum) {
            return 'phone';
        }

        if ($loginType === LoginIdentifierType::Email) {
            return 'email';
        }

        if ($loginType === LoginIdentifierType::Phone) {
            return 'phone';
        }

        return 'email';
    }

    private function loginOtpResendCacheKey(string $fingerprint): string
    {
        return sprintf('otp:login:next_send_at:%s', $fingerprint);
    }

    private function guestScopedFingerprint(LoginIdentifierType $type, string $normalized, string $guestTokenHash): string
    {
        return OtpRepository::fingerprint($type->value, $normalized.'|guest:'.$guestTokenHash);
    }

    private function ensureResendCooldownAllowsSend(string $fingerprint): void
    {
        $seconds = $this->authLogin->otpResendSeconds();
        if ($seconds <= 0) {
            return;
        }

        $nextAt = Cache::get($this->loginOtpResendCacheKey($fingerprint));
        $now = now()->getTimestamp();
        if (! is_int($nextAt) || $now >= $nextAt) {
            return;
        }

        $retryAfter = max(1, $nextAt - $now);
        $response = sendResponse(
            status: false,
            message: __('api.otp_resend_too_soon'),
            data: [
                'retry_after_seconds' => $retryAfter,
            ],
            statusCode: HttpStatus::HTTP_TOO_MANY_REQUESTS,
            additional: ['code' => ApiErrorCode::OtpResendTooSoon->value]
        );

        throw new HttpResponseException(
            $response->header('Retry-After', (string) $retryAfter)
        );
    }

    private function recordResendCooldown(string $fingerprint): void
    {
        $seconds = $this->authLogin->otpResendSeconds();
        if ($seconds <= 0) {
            return;
        }

        $nextAt = now()->getTimestamp() + $seconds;
        Cache::put(
            $this->loginOtpResendCacheKey($fingerprint),
            $nextAt,
            $seconds + 300
        );
    }
}
