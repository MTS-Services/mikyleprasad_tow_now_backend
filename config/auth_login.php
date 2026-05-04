<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Primary login mode
    |--------------------------------------------------------------------------
    |
    | "password" — email/password (or Fortify username field) via /login and /register.
    | "otp" — passwordless login via /otp/request and /otp/verify.
    |
    */
    'login_type' => env('LOGIN_TYPE', 'password'),

    /*
    |--------------------------------------------------------------------------
    | Login OTP identifiers (when login_type=otp)
    |--------------------------------------------------------------------------
    |
    | Comma-separated: email, phone, username. Controls which fields clients may
    | use for passwordless login.
    |
    */
    'login_identifiers' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('LOGIN_IDENTIFIERS', 'email'))
    ))),

    /*
    |--------------------------------------------------------------------------
    | OTP delivery when both email and phone are in login_identifiers
    |--------------------------------------------------------------------------
    |
    | "email" — always send to email. "phone" — SMS (if implemented).
    | "user_choice" — client must pass delivery (email|phone) on /otp/request.
    |
    */
    'otp_delivery' => env('OTP_DELIVERY', 'email'),

    'otp_code_ttl_minutes' => (int) env('OTP_CODE_TTL_MINUTES', 10),

    'otp_code_length' => (int) env('OTP_CODE_LENGTH', 6),

    /*
    |--------------------------------------------------------------------------
    | Minimum seconds between login OTP send attempts (per identifier)
    |--------------------------------------------------------------------------
    |
    | In addition to HTTP throttling. Set to 0 to disable the cooldown.
    |
    */
    'otp_resend_seconds' => (int) env('OTP_RESEND_SECONDS', 60),

    /*
    |--------------------------------------------------------------------------
    | Allow new user creation from login OTP when identifier is unknown
    |--------------------------------------------------------------------------
    |
    | When false, only existing users may request a login code (invite-only).
    |
    */
    'otp_allow_registration_on_login' => filter_var(
        env('OTP_ALLOW_REGISTRATION_ON_LOGIN', 'true'),
        FILTER_VALIDATE_BOOL
    ),
];
