<?php

use App\Enums\OtpPurpose;
use App\Models\User;
use App\Notifications\Auth\OtpCodeNotification;
use App\Services\Otp\OtpRepository;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Config::set('auth_login.login_type', 'password');
    Config::set('auth_login.login_identifiers', ['email']);
    Config::set('auth_login.otp_code_length', 6);
    Config::set('account.password_reset_otp_resend_seconds', 0);
    $this->seed(DatabaseSeeder::class);
});

test('forgot password sends otp notification when user exists', function (): void {
    $user = User::factory()->create(['email' => 'pw-reset@example.com']);

    Notification::fake();

    $this->postJson('/api/v1/forgot-password', [
        'email' => 'pw-reset@example.com',
    ])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.expires_in_minutes', fn ($v) => is_int($v) && $v > 0);

    Notification::assertSentTo($user, OtpCodeNotification::class);
});

test('forgot password responds ok when email unknown and sends nothing', function (): void {
    Notification::fake();

    $this->postJson('/api/v1/forgot-password', [
        'email' => 'nobody@example.com',
    ])
        ->assertOk()
        ->assertJsonPath('success', true);

    Notification::assertNothingSent();
});

test('reset password updates credentials and revokes tokens when code valid', function (): void {
    $user = User::factory()->create(['email' => 'pw-reset-2@example.com']);
    $user->createToken('pre-reset');

    $code = '777888';
    app(OtpRepository::class)->put(
        OtpPurpose::PasswordReset,
        OtpRepository::fingerprint('password_reset', 'pw-reset-2@example.com'),
        [
            'user_id' => $user->id,
            'hash' => OtpRepository::hashCode($code),
        ],
        15
    );

    $this->postJson('/api/v1/reset-password', [
        'email' => 'pw-reset-2@example.com',
        'code' => $code,
        'password' => 'new-password1',
        'password_confirmation' => 'new-password1',
    ])
        ->assertOk()
        ->assertJsonPath('success', true);

    $user->refresh();
    expect(Hash::check('new-password1', $user->password))->toBeTrue();
    expect($user->tokens()->where('revoked', false)->count())->toBe(0);
});

test('reset password rejects invalid code', function (): void {
    $this->postJson('/api/v1/reset-password', [
        'email' => 'user@dev.com',
        'code' => '000000',
        'password' => 'new-password1',
        'password_confirmation' => 'new-password1',
    ])
        ->assertUnprocessable()
        ->assertJsonPath('data.errors.code', fn ($v) => is_array($v) && $v !== []);
});
