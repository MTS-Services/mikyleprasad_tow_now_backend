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
use Laravel\Passport\Passport;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Config::set('auth_login.login_type', 'password');
    Config::set('auth_login.login_identifiers', ['email']);
    Config::set('auth_login.otp_delivery', 'email');
    Config::set('auth_login.otp_resend_seconds', 0);
    $this->seed(DatabaseSeeder::class);
});

test('password login type requires password to start two factor enable', function (): void {
    $user = User::query()->where('email', 'user@dev.com')->firstOrFail();
    Passport::actingAs($user);

    $this->postJson('/api/v1/two-factor/enable', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['password']);
});

test('password login type two factor enable rejects otp field', function (): void {
    $user = User::query()->where('email', 'user@dev.com')->firstOrFail();
    Passport::actingAs($user);

    $this->postJson('/api/v1/two-factor/enable', [
        'password' => 'password',
        'otp' => '123456',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['otp']);
});

test('password login type can start two factor enable with current password', function (): void {
    $user = User::query()->where('email', 'user@dev.com')->firstOrFail();
    Passport::actingAs($user);

    $this->postJson('/api/v1/two-factor/enable', [
        'password' => 'password',
    ])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonStructure(['data' => ['qr_code_svg']]);
});

test('otp login type requires otp to start two factor enable', function (): void {
    Config::set('auth_login.login_type', 'otp');

    $user = User::query()->where('email', 'user@dev.com')->firstOrFail();
    Passport::actingAs($user);

    $this->postJson('/api/v1/two-factor/enable', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['otp']);
});

test('otp login type rejects password on two factor enable', function (): void {
    Config::set('auth_login.login_type', 'otp');

    $user = User::query()->where('email', 'user@dev.com')->firstOrFail();
    Passport::actingAs($user);

    $this->postJson('/api/v1/two-factor/enable', [
        'password' => 'password',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['password']);
});

test('otp login type two factor enable succeeds with step up otp', function (): void {
    Config::set('auth_login.login_type', 'otp');

    $user = User::query()->where('email', 'user@dev.com')->firstOrFail();
    Passport::actingAs($user);

    $code = '989898';
    app(OtpRepository::class)->put(
        OtpPurpose::SensitiveAction,
        OtpRepository::fingerprint('user', (string) $user->id),
        [
            'user_id' => $user->id,
            'hash' => OtpRepository::hashCode($code),
        ],
        10
    );

    $this->postJson('/api/v1/two-factor/enable', [
        'otp' => $code,
    ])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonStructure(['data' => ['qr_code_svg']]);
});

test('otp login type two factor enable accepts integer otp from json', function (): void {
    Config::set('auth_login.login_type', 'otp');

    $user = User::query()->where('email', 'user@dev.com')->firstOrFail();
    Passport::actingAs($user);

    $code = 989898;
    app(OtpRepository::class)->put(
        OtpPurpose::SensitiveAction,
        OtpRepository::fingerprint('user', (string) $user->id),
        [
            'user_id' => $user->id,
            'hash' => OtpRepository::hashCode((string) $code),
        ],
        10
    );

    $this->postJson('/api/v1/two-factor/enable', [
        'otp' => $code,
    ])
        ->assertOk()
        ->assertJsonPath('success', true);
});

test('otp login type two factor enable fails with invalid step up otp', function (): void {
    Config::set('auth_login.login_type', 'otp');

    $user = User::query()->where('email', 'user@dev.com')->firstOrFail();
    Passport::actingAs($user);

    $this->postJson('/api/v1/two-factor/enable', [
        'otp' => '000000',
    ])
        ->assertUnprocessable()
        ->assertJsonPath('data.errors.otp', fn ($v) => is_array($v) && $v !== []);
});

test('reauthentication otp send is disabled when login type is password', function (): void {
    Config::set('auth_login.login_type', 'password');

    $user = User::query()->where('email', 'user@dev.com')->firstOrFail();
    Passport::actingAs($user);

    $this->postJson('/api/v1/two-factor/reauthentication/otp', [])
        ->assertUnprocessable()
        ->assertJsonPath('code', ApiErrorCode::SensitiveActionOtpDisabled->value);
});

test('reauthentication otp send queues notification when login type is otp', function (): void {
    Config::set('auth_login.login_type', 'otp');

    $user = User::query()->where('email', 'user@dev.com')->firstOrFail();
    Passport::actingAs($user);

    Notification::fake();

    $this->postJson('/api/v1/two-factor/reauthentication/otp', [])
        ->assertOk()
        ->assertJsonPath('success', true);

    Notification::assertSentTo($user, OtpCodeNotification::class);
});
