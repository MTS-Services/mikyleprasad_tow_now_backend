<?php

namespace App\Actions\Api\V1\Otp;

use App\Enums\OtpPurpose;
use App\Models\User;
use App\Services\Auth\AuthLoginConfiguration;
use App\Services\Otp\OtpRepository;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class VerifyContactVerificationOtpAction
{
    public function __construct(
        protected AuthLoginConfiguration $authLogin,
        protected OtpRepository $otpRepository,
    ) {}

    public function handle(Request $request, User $user): User
    {
        $channel = $request->string('channel')->toString();
        if ($channel !== 'email' && $channel !== 'phone') {
            throw ValidationException::withMessages([
                'channel' => [__('api.otp_verification_channel_invalid')],
            ]);
        }

        $purpose = $channel === 'email' ? OtpPurpose::VerifyEmail : OtpPurpose::VerifyPhone;
        $fingerprint = OtpRepository::fingerprint('user', (string) $user->id);
        $stored = $this->otpRepository->get($purpose, $fingerprint);

        if ($stored === null) {
            throw ValidationException::withMessages([
                'code' => [__('api.otp_invalid_or_expired')],
            ]);
        }

        $plain = $request->string('code')->toString();
        if (! hash_equals($stored['hash'], OtpRepository::hashCode($plain))) {
            throw ValidationException::withMessages([
                'code' => [__('api.otp_invalid_or_expired')],
            ]);
        }

        if ((int) $stored['user_id'] !== (int) $user->id) {
            $this->otpRepository->forget($purpose, $fingerprint);
            throw ValidationException::withMessages([
                'code' => [__('api.otp_invalid_or_expired')],
            ]);
        }

        $this->otpRepository->forget($purpose, $fingerprint);

        if ($channel === 'email') {
            $user->forceFill(['email_verified_at' => now()])->save();
        } else {
            $user->forceFill(['phone_verified_at' => now()])->save();
        }

        return $user->fresh();
    }
}
