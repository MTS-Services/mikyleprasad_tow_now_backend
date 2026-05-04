<?php

namespace App\Enums;

enum ApiErrorCode: string
{
    case PasswordResetNotAvailable = 'PASSWORD_RESET_NOT_AVAILABLE';
    case LoginOtpDisabled = 'LOGIN_OTP_DISABLED';
    case PasswordLoginDisabled = 'PASSWORD_LOGIN_DISABLED';
    case PasswordRegistrationDisabled = 'PASSWORD_REGISTRATION_DISABLED';
    case SmsOtpNotAvailable = 'SMS_OTP_NOT_AVAILABLE';
    case InvalidOtpPurpose = 'INVALID_OTP_PURPOSE';
    case OtpInvalid = 'OTP_INVALID';

    case OtpResendTooSoon = 'OTP_RESEND_TOO_SOON';

    case SensitiveActionOtpDisabled = 'SENSITIVE_ACTION_OTP_DISABLED';
}
