<?php

return [

    'deletion_grace_days' => (int) env('ACCOUNT_DELETION_GRACE_DAYS', 30),

    'restore_otp_ttl_minutes' => (int) env('ACCOUNT_RESTORE_OTP_TTL_MINUTES', 15),

    'restore_otp_resend_seconds' => (int) env('ACCOUNT_RESTORE_OTP_RESEND_SECONDS', 60),

    'password_reset_otp_ttl_minutes' => (int) env('PASSWORD_RESET_OTP_TTL_MINUTES', 15),

    'password_reset_otp_resend_seconds' => (int) env('PASSWORD_RESET_OTP_RESEND_SECONDS', 60),

    'two_factor_login_token_ttl_minutes' => (int) env('TWO_FACTOR_LOGIN_TOKEN_TTL_MINUTES', 5),

];
