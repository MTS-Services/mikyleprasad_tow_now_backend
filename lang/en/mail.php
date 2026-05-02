<?php

return [

    'password_reset_otp' => [
        'subject' => 'Your password reset code',
        'title' => 'Password reset',
        'intro' => 'Use the code below to reset your password. If you did not request this, you can ignore this email.',
        'expires' => 'This code expires in :minutes minutes.',
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
