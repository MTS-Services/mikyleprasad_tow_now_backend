<?php

use App\Enums\ApiErrorCode;
use App\Enums\OtpPurpose;
use App\Models\User;
use App\Notifications\Auth\OtpCodeNotification;
use App\Services\Otp\OtpRepository;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Notification;
use Laravel\Fortify\Fortify;
use Laravel\Passport\Passport;
use PragmaRX\Google2FA\Google2FA;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Config::set('auth_login.login_type', 'password');
    Config::set('auth_login.login_identifiers', ['email']);
    Config::set('auth_login.otp_delivery', 'email');
    Config::set('auth_login.otp_resend_seconds', 0);
    Config::set('auth_login.otp_allow_registration_on_login', true);
    $this->seed(DatabaseSeeder::class);
    $this->withHeader('X-Guest-Token', 'guest-test-token');
});

test('password mode rejects login otp request with code', function (): void {
    Config::set('auth_login.login_type', 'password');

    $this->postJson('/api/v1/otp/request', [
        'identifier' => 'user@dev.com',
    ])
        ->assertUnprocessable()
        ->assertJsonPath('code', ApiErrorCode::LoginOtpDisabled->value);
});

test('password mode rejects login otp verify with code', function (): void {
    Config::set('auth_login.login_type', 'password');

    $this->postJson('/api/v1/otp/verify', [
        'identifier' => 'user@dev.com',
        'code' => '123456',
    ])
        ->assertUnprocessable();
});

test('single email identifier accepts email field without identifier key on otp request', function (): void {
    Config::set('auth_login.login_type', 'otp');
    Config::set('auth_login.login_identifiers', ['email']);

    Notification::fake();

    $this->postJson('/api/v1/otp/request', [
        'email' => 'user@dev.com',
    ])
        ->assertOk();

    $user = User::query()->where('email', 'user@dev.com')->firstOrFail();
    Notification::assertSentTo($user, OtpCodeNotification::class);
});

test('single email identifier validation error targets email when body is empty', function (): void {
    Config::set('auth_login.login_type', 'otp');
    Config::set('auth_login.login_identifiers', ['email']);

    $this->postJson('/api/v1/otp/request', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email'])
        ->assertJsonMissingValidationErrors(['identifier']);
});

test('otp mode login without password sends otp same as otp request', function (): void {
    Config::set('auth_login.login_type', 'otp');
    Config::set('auth_login.login_identifiers', ['email']);

    Notification::fake();

    $this->postJson('/api/v1/login', [
        'email' => 'user@dev.com',
    ])
        ->assertOk()
        ->assertJsonPath('success', true);

    $user = User::query()->where('email', 'user@dev.com')->firstOrFail();
    Notification::assertSentTo($user, OtpCodeNotification::class);
});

test('otp mode login ignores password and still sends otp', function (): void {
    Config::set('auth_login.login_type', 'otp');
    Config::set('auth_login.login_identifiers', ['email']);

    Notification::fake();

    $this->postJson('/api/v1/login', [
        'email' => 'user@dev.com',
        'password' => 'wrong-password',
    ])
        ->assertOk();

    $user = User::query()->where('email', 'user@dev.com')->firstOrFail();
    Notification::assertSentTo($user, OtpCodeNotification::class);
});

test('otp mode allows registration', function (): void {
    Config::set('auth_login.login_type', 'otp');

    Notification::fake();

    $this->postJson('/api/v1/register', [
        'role' => 'user',
        'email' => 'x@example.com',
    ])
        ->assertCreated()
        ->assertJsonPath('data.identifier', 'x@example.com')
        ->assertJsonMissingPath('data.access_token');

    expect(User::query()->where('email', 'x@example.com')->exists())->toBeTrue();
});

test('otp mode rejects forgot and reset password with code', function (): void {
    Config::set('auth_login.login_type', 'otp');

    $this->postJson('/api/v1/forgot-password', ['email' => 'user@dev.com'])
        ->assertForbidden()
        ->assertJsonPath('code', ApiErrorCode::PasswordResetNotAvailable->value);

    $this->postJson('/api/v1/reset-password', [
        'code' => '123456',
        'email' => 'user@dev.com',
        'password' => 'password1',
        'password_confirmation' => 'password1',
    ])
        ->assertForbidden()
        ->assertJsonPath('code', ApiErrorCode::PasswordResetNotAvailable->value);
});

test('otp verify returns token when otp matches cache', function (): void {
    Config::set('auth_login.login_type', 'otp');
    Config::set('auth_login.login_identifiers', ['email']);

    $user = User::factory()->create(['email' => 'otp-user@dev.com']);
    $code = '424242';
    app(OtpRepository::class)->put(
        OtpPurpose::Login,
        OtpRepository::fingerprint('email', 'otp-user@dev.com|guest:'.hash('sha256', 'guest-test-token')),
        [
            'user_id' => $user->id,
            'hash' => OtpRepository::hashCode($code),
            'guest_token_hash' => hash('sha256', 'guest-test-token'),
        ],
        10
    );

    $this->postJson('/api/v1/otp/verify', [
        'identifier' => 'otp-user@dev.com',
        'code' => $code,
        'device_name' => 'PHPUnit',
    ])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.user.email', 'otp-user@dev.com')
        ->assertJsonPath('data.access_token', fn ($v) => is_string($v) && $v !== '');
});

test('otp verify marks an unverified identifier before returning token', function (): void {
    Config::set('auth_login.login_type', 'otp');
    Config::set('auth_login.login_identifiers', ['email']);

    $user = User::factory()->create([
        'email' => 'unverified-otp@example.com',
        'email_verified_at' => null,
    ]);
    $code = '525252';
    app(OtpRepository::class)->put(
        OtpPurpose::Login,
        OtpRepository::fingerprint('email', 'unverified-otp@example.com|guest:'.hash('sha256', 'guest-test-token')),
        [
            'user_id' => $user->id,
            'hash' => OtpRepository::hashCode($code),
            'guest_token_hash' => hash('sha256', 'guest-test-token'),
        ],
        10
    );

    $this->postJson('/api/v1/otp/verify', [
        'identifier' => 'unverified-otp@example.com',
        'code' => $code,
    ])
        ->assertOk()
        ->assertJsonPath('data.user.email_verified_at', fn ($value): bool => is_string($value) && $value !== '')
        ->assertJsonPath('data.access_token', fn ($value): bool => is_string($value) && $value !== '');
});

test('login otp resend cooldown returns 429 then allows after window', function (): void {
    Config::set('auth_login.login_type', 'otp');
    Config::set('auth_login.login_identifiers', ['email']);
    Config::set('auth_login.otp_resend_seconds', 2);

    Notification::fake();

    $this->postJson('/api/v1/otp/request', [
        'identifier' => 'user@dev.com',
    ])->assertOk();

    $this->postJson('/api/v1/otp/request', [
        'identifier' => 'user@dev.com',
    ])
        ->assertStatus(429)
        ->assertHeader('Retry-After')
        ->assertJsonPath('code', ApiErrorCode::OtpResendTooSoon->value)
        ->assertJsonPath('data.retry_after_seconds', fn ($v) => is_int($v) || is_numeric($v));

    $this->travel(3)->seconds();

    $this->postJson('/api/v1/otp/request', [
        'identifier' => 'user@dev.com',
    ])->assertOk();
});

test('login otp resend cooldown is scoped to the guest token', function (): void {
    Config::set('auth_login.login_type', 'otp');
    Config::set('auth_login.login_identifiers', ['email']);
    Config::set('auth_login.otp_resend_seconds', 60);

    Notification::fake();

    $this->withHeader('X-Guest-Token', 'profile-a')
        ->postJson('/api/v1/otp/request', [
            'identifier' => 'user@dev.com',
        ])->assertOk();

    $this->withHeader('X-Guest-Token', 'profile-b')
        ->postJson('/api/v1/otp/request', [
            'identifier' => 'user@dev.com',
        ])->assertOk();

    $user = User::query()->where('email', 'user@dev.com')->firstOrFail();
    Notification::assertSentToTimes($user, OtpCodeNotification::class, 2);
});

test('otp resend route mirrors request endpoint', function (): void {
    Config::set('auth_login.login_type', 'otp');
    Config::set('auth_login.login_identifiers', ['email']);

    Notification::fake();

    $this->postJson('/api/v1/otp/resend', [
        'identifier' => 'user@dev.com',
    ])->assertOk();

    $user = User::query()->where('email', 'user@dev.com')->firstOrFail();
    Notification::assertSentTo($user, OtpCodeNotification::class);
});

test('otp login rejects unknown identifier', function (): void {
    Config::set('auth_login.login_type', 'otp');
    Config::set('auth_login.login_identifiers', ['email']);
    Config::set('auth_login.otp_allow_registration_on_login', false);

    $this->postJson('/api/v1/otp/request', [
        'identifier' => 'unknown-person@example.com',
    ])
        ->assertUnauthorized()
        ->assertJsonPath('success', false);
});

test('otp login requests notification for existing user', function (): void {
    Config::set('auth_login.login_type', 'otp');
    Config::set('auth_login.login_identifiers', ['email']);

    Notification::fake();

    $this->postJson('/api/v1/otp/request', [
        'identifier' => 'user@dev.com',
    ])->assertOk();

    $user = User::query()->where('email', 'user@dev.com')->firstOrFail();

    Notification::assertSentTo($user, OtpCodeNotification::class);
});

test('sms otp returns unauthorized for an unknown phone identifier', function (): void {
    Config::set('auth_login.login_type', 'otp');
    Config::set('auth_login.login_identifiers', ['phone']);

    $this->postJson('/api/v1/otp/request', [
        'identifier' => '+15551234567',
        'name' => 'Caller',
    ])
        ->assertUnauthorized()
        ->assertJsonPath('success', false);
});

test('user_choice requires delivery when email and phone identifiers are enabled', function (): void {
    Config::set('auth_login.login_type', 'otp');
    Config::set('auth_login.login_identifiers', ['email', 'phone']);
    Config::set('auth_login.otp_delivery', 'user_choice');

    $this->postJson('/api/v1/otp/request', [
        'identifier' => 'user@dev.com',
    ])->assertUnprocessable();
});

test('otp request classifies plain string as username when email and username identifiers are allowed', function (): void {
    Config::set('auth_login.login_type', 'otp');
    Config::set('auth_login.login_identifiers', ['email', 'username']);

    Notification::fake();

    $user = User::factory()->create([
        'username' => 'johndoe',
        'email' => 'johndoe-mail@example.com',
    ]);

    $this->postJson('/api/v1/otp/request', [
        'identifier' => 'johndoe',
    ])->assertOk();

    Notification::assertSentTo($user, OtpCodeNotification::class);
});

test('password login requires two factor when enabled', function (): void {
    Config::set('auth_login.login_type', 'password');

    $user = User::factory()->create(['email' => 'twofa-pass@example.com']);
    $user->forceFill([
        'two_factor_secret' => Fortify::currentEncrypter()->encrypt('JBSWY3DPEHPK3PXP'),
        'two_factor_confirmed_at' => now(),
    ])->save();

    $google2fa = new Google2FA;
    $code = $google2fa->getCurrentOtp('JBSWY3DPEHPK3PXP');

    $login = $this->postJson('/api/v1/login', [
        'email' => 'twofa-pass@example.com',
        'password' => 'password',
    ])
        ->assertOk()
        ->assertJsonPath('data.two_factor', true)
        ->assertJsonMissingPath('data.access_token');

    $token = $login->json('data.two_factor_token');

    $this->postJson('/api/v1/two-factor-challenge', [
        'two_factor_token' => $token,
        'code' => $code,
    ])
        ->assertOk()
        ->assertJsonPath('data.access_token', fn ($v) => is_string($v) && $v !== '');
});

test('otp verify requires two factor when enabled', function (): void {
    Config::set('auth_login.login_type', 'otp');
    Config::set('auth_login.login_identifiers', ['email']);

    $user = User::factory()->create(['email' => 'twofa-otp@example.com']);
    $user->forceFill([
        'two_factor_secret' => Fortify::currentEncrypter()->encrypt('JBSWY3DPEHPK3PXP'),
        'two_factor_confirmed_at' => now(),
    ])->save();

    $google2fa = new Google2FA;
    $code = $google2fa->getCurrentOtp('JBSWY3DPEHPK3PXP');

    $otp = '919191';
    app(OtpRepository::class)->put(
        OtpPurpose::Login,
        OtpRepository::fingerprint('email', 'twofa-otp@example.com|guest:'.hash('sha256', 'guest-test-token')),
        [
            'user_id' => $user->id,
            'hash' => OtpRepository::hashCode($otp),
            'guest_token_hash' => hash('sha256', 'guest-test-token'),
        ],
        10
    );

    $twoFaToken = $this->postJson('/api/v1/otp/verify', [
        'identifier' => 'twofa-otp@example.com',
        'code' => $otp,
    ])
        ->assertOk()
        ->json('data.two_factor_token');

    $this->postJson('/api/v1/two-factor-challenge', [
        'two_factor_token' => $twoFaToken,
        'code' => $code,
    ])
        ->assertOk()
        ->assertJsonPath('data.access_token', fn ($v) => is_string($v) && $v !== '');
});

test('authenticated user can request email verification otp', function (): void {
    Config::set('auth_login.login_type', 'password');

    $user = User::factory()->create(['email' => 'verify-email@example.com']);
    Passport::actingAs($user);

    Notification::fake();

    $this->postJson('/api/v1/verification/otp/request', [
        'channel' => 'email',
    ])->assertOk();

    Notification::assertSentTo($user, OtpCodeNotification::class);
});

test('phone verification otp request returns sms unavailable', function (): void {
    $user = User::factory()->create([
        'email' => 'verify-phone@example.com',
        'phone' => '+15550001111',
    ]);

    Passport::actingAs($user);

    $this->postJson('/api/v1/verification/otp/request', [
        'channel' => 'phone',
    ])
        ->assertStatus(503)
        ->assertJsonPath('code', ApiErrorCode::SmsOtpNotAvailable->value);
});
