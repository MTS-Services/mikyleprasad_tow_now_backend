<?php

namespace App\Enums;

enum OtpPurpose: string
{
    case Login = 'login';
    case VerifyEmail = 'verify_email';
    case VerifyPhone = 'verify_phone';

    /** Step-up verification before sensitive account actions (e.g. enabling 2FA for passwordless users). */
    case SensitiveAction = 'sensitive_action';

    case PasswordReset = 'password_reset';
}
