<?php

namespace App\Actions\Api\V1\Otp;

use App\Enums\OtpPurpose;
use App\Models\User;
use App\Services\Otp\OtpRepository;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class VerifySensitiveActionOtpAction
{
    public function __construct(
        protected OtpRepository $otpRepository,
    ) {}

    /**
     * Verify the step-up OTP for the authenticated user and consume it (single-use).
     */
    public function handle(Request $request, User $user): void
    {
        $plain = $request->string('otp')->toString();
        $fingerprint = OtpRepository::fingerprint('user', (string) $user->id);
        $stored = $this->otpRepository->get(OtpPurpose::SensitiveAction, $fingerprint);

        if ($stored === null || $stored['user_id'] !== $user->id) {
            throw ValidationException::withMessages([
                'otp' => [__('api.otp_invalid_or_expired')],
            ]);
        }

        $actual = OtpRepository::hashCode($plain);

        if (! hash_equals($stored['hash'], $actual)) {
            throw ValidationException::withMessages([
                'otp' => [__('api.otp_invalid_or_expired')],
            ]);
        }

        $this->otpRepository->forget(OtpPurpose::SensitiveAction, $fingerprint);
    }
}
