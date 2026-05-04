<?php

namespace App\Actions\Api\V1\Otp;

use App\Enums\ApiErrorCode;
use App\Enums\OtpPurpose;
use App\Models\User;
use App\Notifications\Auth\OtpCodeNotification;
use App\Services\Auth\AuthLoginConfiguration;
use App\Services\Otp\OtpRepository;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response as HttpStatus;

class RequestContactVerificationOtpAction
{
    public function __construct(
        protected AuthLoginConfiguration $authLogin,
        protected OtpRepository $otpRepository,
    ) {}

    public function handle(Request $request, User $user): JsonResponse
    {
        $channel = $request->string('channel')->toString();
        if ($channel !== 'email' && $channel !== 'phone') {
            throw ValidationException::withMessages([
                'channel' => [__('api.otp_verification_channel_invalid')],
            ]);
        }

        if ($channel === 'email' && ($user->email === null || $user->email === '')) {
            throw ValidationException::withMessages([
                'channel' => [__('api.otp_verification_email_missing')],
            ]);
        }

        if ($channel === 'phone' && ($user->phone === null || $user->phone === '')) {
            throw ValidationException::withMessages([
                'channel' => [__('api.otp_verification_phone_missing')],
            ]);
        }

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

        $purpose = OtpPurpose::VerifyEmail;
        $fingerprint = OtpRepository::fingerprint('user', (string) $user->id);

        $length = $this->authLogin->otpCodeLength();
        $min = 10 ** ($length - 1);
        $max = (10 ** $length) - 1;
        $plainCode = (string) random_int((int) $min, (int) $max);

        $this->otpRepository->put(
            $purpose,
            $fingerprint,
            [
                'user_id' => $user->id,
                'hash' => OtpRepository::hashCode($plainCode),
            ],
            $this->authLogin->otpTtlMinutes()
        );

        $user->notify(new OtpCodeNotification($plainCode, $purpose));

        return sendResponse(
            status: true,
            message: __('api.otp_resent_to_email'),
            data: [
                'expires_in_minutes' => $this->authLogin->otpTtlMinutes(),
            ],
            statusCode: HttpStatus::HTTP_OK
        );
    }
}
