<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Branded HTML OTP emails (resources/views/mail/otp-modern.blade.php)
    |--------------------------------------------------------------------------
    */
    'otp_modern' => [
        'badge' => 'Verification',
        'code_label' => 'Your code',
        'expires' => 'This code expires in :minutes minutes. Do not share it with anyone.',
        'security_note' => 'If you did not request this email, secure your account and contact support.',

        'login' => [
            'subject' => 'Your :app sign-in code',
            'preheader' => 'Use your one-time code to finish signing in.',
            'title' => 'Sign in to your account',
            'intro' => "You're signing in to :app. Enter the code below to continue. If you didn't try to sign in, you can safely ignore this message.",
        ],
        'verify_email' => [
            'subject' => 'Confirm your email on :app',
            'preheader' => 'Verify your email address with this code.',
            'title' => 'Confirm your email',
            'intro' => "Use this code to verify your email address on :app. If you didn't start this, ignore this email.",
        ],
        'verify_phone' => [
            'subject' => 'Confirm your phone on :app',
            'preheader' => 'Your phone verification code is ready.',
            'title' => 'Confirm your phone number',
            'intro' => "Use this code to verify your phone on :app. If you didn't request this, ignore this email.",
        ],
        'sensitive_action' => [
            'subject' => 'Security verification for :app',
            'preheader' => 'Confirm this sensitive change with your code.',
            'title' => 'Confirm this security step',
            'intro' => 'Someone is changing security settings on your :app account. Enter the code below only if this was you.',
        ],
        'password_reset' => [
            'subject' => 'Reset your :app password',
            'preheader' => 'Use this code to choose a new password.',
            'title' => 'Reset your password',
            'intro' => "We received a request to reset the password for your :app account. Enter the code below in the app, then choose a new password. If you didn't ask for this, you can ignore this email.",
        ],
    ],

    'account_restore_otp' => [
        'subject' => 'Account deletion cancellation code',
        'title' => 'Cancel account deletion',
        'intro' => 'Use the code below to cancel the scheduled deletion of your account.',
        'expires' => 'This code expires in :minutes minutes.',
    ],

    'welcome' => [
        'subject' => 'Welcome to SourceNest',
        'preheader' => 'Your SourceNest account is ready. Start exploring suppliers today.',
    ],

];
