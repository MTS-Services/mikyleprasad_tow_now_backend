<?php

namespace App\Services\Auth;

use App\Enums\LoginIdentifierType;
use App\Enums\LoginType;
use App\Enums\OtpDeliveryPreference;
use InvalidArgumentException;

class AuthLoginConfiguration
{
    public function loginType(): LoginType
    {
        $value = (string) config('auth_login.login_type', 'password');

        return LoginType::tryFrom($value) ?? throw new InvalidArgumentException("Invalid LOGIN_TYPE: {$value}");
    }

    /**
     * @return list<LoginIdentifierType>
     */
    public function loginIdentifierTypes(): array
    {
        $raw = config('auth_login.login_identifiers', ['email']);
        if (! is_array($raw)) {
            $raw = ['email'];
        }

        $out = [];
        foreach ($raw as $item) {
            $t = LoginIdentifierType::tryFrom((string) $item);
            if ($t !== null) {
                $out[] = $t;
            }
        }

        if ($out === []) {
            $out[] = LoginIdentifierType::Email;
        }

        return $out;
    }

    public function hasLoginIdentifier(LoginIdentifierType $type): bool
    {
        return in_array($type, $this->loginIdentifierTypes(), true);
    }

    public function bothEmailAndPhoneLoginIdentifiers(): bool
    {
        return $this->hasLoginIdentifier(LoginIdentifierType::Email)
            && $this->hasLoginIdentifier(LoginIdentifierType::Phone);
    }

    public function otpDeliveryPreference(): OtpDeliveryPreference
    {
        $value = (string) config('auth_login.otp_delivery', 'email');

        return OtpDeliveryPreference::tryFrom($value) ?? OtpDeliveryPreference::Email;
    }

    public function otpTtlMinutes(): int
    {
        return max(1, (int) config('auth_login.otp_code_ttl_minutes', 10));
    }

    public function otpCodeLength(): int
    {
        $len = (int) config('auth_login.otp_code_length', 6);

        return max(4, min(10, $len));
    }

    public function otpResendSeconds(): int
    {
        return max(0, (int) config('auth_login.otp_resend_seconds', 60));
    }

    public function otpAllowRegistrationOnLogin(): bool
    {
        return (bool) config('auth_login.otp_allow_registration_on_login', true);
    }
}
