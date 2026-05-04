<?php

namespace App\Actions\Api\V1\Otp;

use App\Enums\ApiErrorCode;
use App\Enums\LoginIdentifierType;
use App\Enums\LoginType;
use App\Enums\OtpDeliveryPreference;
use App\Enums\OtpPurpose;
use App\Models\User;
use App\Notifications\Auth\OtpCodeNotification;
use App\Services\Auth\AuthLoginConfiguration;
use App\Services\Otp\OtpRepository;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response as HttpStatus;

class RequestSensitiveActionOtpAction
{
    public function __construct(
        protected AuthLoginConfiguration $authLogin,
        protected OtpRepository $otpRepository,
    ) {}

    public function handle(Request $request, User $user): JsonResponse
    {
        if ($this->authLogin->loginType() !== LoginType::Otp) {
            throw new HttpResponseException(
                sendResponse(
                    status: false,
                    message: __('api.sensitive_action_otp_disabled'),
                    data: null,
                    statusCode: HttpStatus::HTTP_UNPROCESSABLE_ENTITY,
                    additional: ['code' => ApiErrorCode::SensitiveActionOtpDisabled->value]
                )
            );
        }

        $fingerprint = OtpRepository::fingerprint('user', (string) $user->id);

        $this->ensureResendCooldownAllowsSend($fingerprint);

        $channel = $this->resolveOutboundChannel($request, $user);

        if ($channel === 'phone') {
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

        if ($user->email === null || $user->email === '') {
            throw ValidationException::withMessages([
                'email' => [__('api.otp_email_required_for_delivery')],
            ]);
        }

        $length = $this->authLogin->otpCodeLength();
        $min = 10 ** ($length - 1);
        $max = (10 ** $length) - 1;
        $plainCode = (string) random_int((int) $min, (int) $max);

        $this->otpRepository->put(
            OtpPurpose::SensitiveAction,
            $fingerprint,
            [
                'user_id' => $user->id,
                'hash' => OtpRepository::hashCode($plainCode),
            ],
            $this->authLogin->otpTtlMinutes()
        );

        $user->notify(new OtpCodeNotification($plainCode, OtpPurpose::SensitiveAction));

        $this->recordResendCooldown($fingerprint);

        return sendResponse(
            status: true,
            message: __('api.otp_resent_to_email'),
            data: [
                'expires_in_minutes' => $this->authLogin->otpTtlMinutes(),
            ],
            statusCode: HttpStatus::HTTP_OK
        );
    }

    /**
     * Resolves email vs SMS using the same rules as login OTP delivery (`OTP_DELIVERY`, `LOGIN_IDENTIFIERS`).
     *
     * @return 'email'|'phone'
     */
    private function resolveOutboundChannel(Request $request, User $user): string
    {
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

            if ($choice === 'email' && ($user->email === null || $user->email === '')) {
                throw ValidationException::withMessages([
                    'delivery' => [__('api.otp_verification_email_missing')],
                ]);
            }

            if ($choice === 'phone' && ($user->phone === null || $user->phone === '')) {
                throw ValidationException::withMessages([
                    'delivery' => [__('api.otp_verification_phone_missing')],
                ]);
            }

            return $choice;
        }

        if ($preference === OtpDeliveryPreference::Phone) {
            return 'phone';
        }

        if ($user->email !== null && $user->email !== '') {
            return 'email';
        }

        if ($user->phone !== null && $user->phone !== '') {
            return 'phone';
        }

        return 'email';
    }

    private function sensitiveOtpResendCacheKey(string $fingerprint): string
    {
        return sprintf('otp:sensitive_action:next_send_at:%s', $fingerprint);
    }

    private function ensureResendCooldownAllowsSend(string $fingerprint): void
    {
        $seconds = $this->authLogin->otpResendSeconds();
        if ($seconds <= 0) {
            return;
        }

        $nextAt = Cache::get($this->sensitiveOtpResendCacheKey($fingerprint));
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
            $this->sensitiveOtpResendCacheKey($fingerprint),
            $nextAt,
            $seconds + 300
        );
    }
}
