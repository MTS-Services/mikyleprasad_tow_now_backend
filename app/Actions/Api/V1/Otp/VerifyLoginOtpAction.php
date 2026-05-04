<?php

namespace App\Actions\Api\V1\Otp;

use App\Enums\LoginType;
use App\Enums\OtpPurpose;
use App\Models\User;
use App\Services\Auth\AuthLoginConfiguration;
use App\Services\Auth\LoginIdentifierDetector;
use App\Services\Otp\OtpRepository;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class VerifyLoginOtpAction
{
    public function __construct(
        protected AuthLoginConfiguration $authLogin,
        protected OtpRepository $otpRepository,
        protected LoginIdentifierDetector $loginIdentifierDetector,
    ) {}

    public function handle(Request $request): User
    {
        if ($this->authLogin->loginType() !== LoginType::Otp) {
            throw ValidationException::withMessages([
                'otp' => [__('api.login_otp_disabled')],
            ]);
        }

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

        $fingerprint = OtpRepository::fingerprint($type->value, $normalized);
        $stored = $this->otpRepository->get(OtpPurpose::Login, $fingerprint);

        if ($stored === null) {
            throw ValidationException::withMessages([
                'code' => [__('api.otp_invalid_or_expired')],
            ]);
        }

        $plain = $request->string('code')->toString();
        $expected = $stored['hash'];
        $actual = OtpRepository::hashCode($plain);

        if (! hash_equals($expected, $actual)) {
            throw ValidationException::withMessages([
                'code' => [__('api.otp_invalid_or_expired')],
            ]);
        }

        $user = User::query()->find($stored['user_id']);

        if ($user === null) {
            $this->otpRepository->forget(OtpPurpose::Login, $fingerprint);
            throw ValidationException::withMessages([
                'code' => [__('api.otp_invalid_or_expired')],
            ]);
        }

        $this->otpRepository->forget(OtpPurpose::Login, $fingerprint);

        return $user;
    }
}
