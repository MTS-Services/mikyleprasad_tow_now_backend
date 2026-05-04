<?php

namespace App\Actions\Api\V1\Auth;

use App\Enums\ApiErrorCode;
use App\Enums\OtpPurpose;
use App\Models\User;
use App\Notifications\Auth\OtpCodeNotification;
use App\Services\Auth\AuthLoginConfiguration;
use App\Services\Otp\OtpRepository;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response as HttpStatus;

class RequestPasswordResetOtpAction
{
    public function __construct(
        protected OtpRepository $otpRepository,
        protected AuthLoginConfiguration $authLogin,
    ) {}

    public function handle(Request $request): JsonResponse
    {
        $email = strtolower($request->string('email')->toString());
        $fingerprint = OtpRepository::fingerprint('password_reset', $email);

        $this->ensureResendCooldownAllowsSend($fingerprint);

        $user = User::query()->where('email', $email)->first();

        if ($user !== null) {
            $length = $this->authLogin->otpCodeLength();
            $min = 10 ** ($length - 1);
            $max = (10 ** $length) - 1;
            $plainCode = (string) random_int((int) $min, (int) $max);
            $ttl = max(1, (int) config('account.password_reset_otp_ttl_minutes', 15));

            $this->otpRepository->put(
                OtpPurpose::PasswordReset,
                $fingerprint,
                [
                    'user_id' => $user->id,
                    'hash' => OtpRepository::hashCode($plainCode),
                ],
                $ttl
            );

            $user->notify(new OtpCodeNotification($plainCode, OtpPurpose::PasswordReset));
        }

        $this->recordResendCooldown($fingerprint);

        $ttlMinutes = max(1, (int) config('account.password_reset_otp_ttl_minutes', 15));

        return sendResponse(
            status: true,
            message: __('api.password_reset_code_sent'),
            data: [
                'expires_in_minutes' => $ttlMinutes,
            ],
            statusCode: HttpStatus::HTTP_OK
        );
    }

    private function passwordResetResendCacheKey(string $fingerprint): string
    {
        return sprintf('otp:password_reset:next_send_at:%s', $fingerprint);
    }

    private function ensureResendCooldownAllowsSend(string $fingerprint): void
    {
        $seconds = max(0, (int) config('account.password_reset_otp_resend_seconds', 60));
        if ($seconds <= 0) {
            return;
        }

        $nextAt = Cache::get($this->passwordResetResendCacheKey($fingerprint));
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
        $seconds = max(0, (int) config('account.password_reset_otp_resend_seconds', 60));
        if ($seconds <= 0) {
            return;
        }

        $nextAt = now()->getTimestamp() + $seconds;
        Cache::put(
            $this->passwordResetResendCacheKey($fingerprint),
            $nextAt,
            $seconds + 300
        );
    }
}
