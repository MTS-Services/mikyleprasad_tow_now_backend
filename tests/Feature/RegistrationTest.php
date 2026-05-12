<?php

use App\Enums\OtpPurpose;
use App\Enums\UserRole;
use App\Models\User;
use App\Models\UserNotification;
use App\Notifications\Auth\OtpCodeNotification;
use App\Services\Otp\OtpRepository;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Config::set('auth_login.login_type', 'password');
    Config::set('broadcasting.default', 'log');
    $this->seed(DatabaseSeeder::class);
    $this->withHeader('X-Guest-Token', 'guest-test-token');
});

test('user registration requires role and email and generates a visual username', function (): void {
    Notification::fake();

    $response = $this->postJson('/api/v1/register', [
        'role' => UserRole::USER->value,
        'email' => 'customer@example.com',
    ])
        ->assertCreated()
        ->assertJsonMissingPath('data.access_token');

    $response->assertJsonStructure(['success', 'message', 'data' => ['expires_in_minutes']]);
    expect($response->json('data.expires_in_minutes'))->toBeInt()->toBeGreaterThan(0);

    $user = User::query()->where('email', 'customer@example.com')->firstOrFail();

    expect($user->email_verified_at)->toBeNull();
    expect($user->username)->toBeString()->toStartWith('USR-');

    Notification::assertSentTo($user, OtpCodeNotification::class);
});

test('admin cannot register', function (): void {
    $this->postJson('/api/v1/register', [
        'role' => UserRole::ADMIN->value,
        'email' => 'admin-register@example.com',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['role']);
});

test('driver registration requires profile fields', function (): void {
    $this->postJson('/api/v1/register', [
        'role' => UserRole::DRIVER->value,
        'email' => 'driver-missing@example.com',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'name',
            'phone',
            'address',
            'car_name',
            'brand',
            'model',
            'license_plate',
            'truck_image',
            'driving_license_image',
        ]);
});

test('driver registration creates related profile and stores documents', function (): void {
    Notification::fake();
    Storage::fake('public');

    $this->postJson('/api/v1/register', [
        'role' => UserRole::DRIVER->value,
        'email' => 'driver@example.com',
        'name' => 'Casey Driver',
        'phone' => '+15551234567',
        'address' => '123 Main St, Anytown, USA',
        'bio' => 'I am a driver',
        'car_name' => 'Ford',
        'brand' => 'Ford',
        'model' => 'F-550',
        'capacity' => '10000',
        'license_plate' => 'TOW-1234',
        'truck_image' => UploadedFile::fake()->image('truck.png'),
        'driving_license_image' => UploadedFile::fake()->image('license.png'),
        'legal_documents' => UploadedFile::fake()->image('documents.png'),
    ])
        ->assertCreated()
        ->assertJsonMissingPath('data.access_token')
        ->assertJsonStructure(['data' => ['expires_in_minutes']]);

    $user = User::query()->where('email', 'driver@example.com')->firstOrFail();
    $vehicle = $user->vehicle()->firstOrFail();

    Storage::disk('public')->assertExists($vehicle->truck_image);
    Storage::disk('public')->assertExists($vehicle->driving_license_image);
    Storage::disk('public')->assertExists($vehicle->legal_documents);
    Notification::assertSentTo($user, OtpCodeNotification::class);
});

test('registration verify creates user and returns access token', function (): void {
    Notification::fake();

    $this->postJson('/api/v1/register', [
        'role' => UserRole::USER->value,
        'email' => 'needs-verification@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertCreated();

    $this->postJson('/api/v1/login', [
        'email' => 'needs-verification@example.com',
        'password' => 'password',
    ])
        ->assertForbidden()
        ->assertJsonPath('code', 'IDENTIFIER_NOT_VERIFIED');

    $code = '123456';
    $user = User::query()->where('email', 'needs-verification@example.com')->firstOrFail();
    app(OtpRepository::class)->put(
        OtpPurpose::VerifyEmail,
        OtpRepository::fingerprint('email', 'needs-verification@example.com|guest:'.hash('sha256', 'guest-test-token')),
        [
            'user_id' => $user->id,
            'hash' => OtpRepository::hashCode($code),
            'guest_token_hash' => hash('sha256', 'guest-test-token'),
        ],
        10
    );

    $this->postJson('/api/v1/otp/register/verify', [
        'email' => 'needs-verification@example.com',
        'code' => $code,
    ])
        ->assertCreated()
        ->assertJsonPath('data.user.email', 'needs-verification@example.com')
        ->assertJsonPath('data.user.email_verified_at', fn ($value): bool => is_string($value) && $value !== '')
        ->assertJsonPath('data.access_token', fn ($value): bool => is_string($value) && $value !== '');

    expect($user->username)->not->toBeNull();
});

test('registration verify notifies admins with in-app notification', function (): void {
    Notification::fake();

    User::factory()->admin()->create(['email' => 'admin-reg-notify@example.com']);

    $this->postJson('/api/v1/register', [
        'role' => UserRole::USER->value,
        'email' => 'admin-notify-user@example.com',
    ])->assertCreated();

    $user = User::query()->where('email', 'admin-notify-user@example.com')->firstOrFail();
    app(OtpRepository::class)->put(
        OtpPurpose::VerifyEmail,
        OtpRepository::fingerprint('email', 'admin-notify-user@example.com|guest:'.hash('sha256', 'guest-test-token')),
        [
            'user_id' => $user->id,
            'hash' => OtpRepository::hashCode('424242'),
            'guest_token_hash' => hash('sha256', 'guest-test-token'),
        ],
        10
    );

    $this->postJson('/api/v1/otp/register/verify', [
        'email' => 'admin-notify-user@example.com',
        'code' => '424242',
    ])->assertCreated();

    $admin = User::query()->where('email', 'admin-reg-notify@example.com')->firstOrFail();
    expect(UserNotification::query()->where('user_id', $admin->id)->where('type', 'user.registered')->exists())->toBeTrue();
});

test('registration otp can be resent before verification', function (): void {
    Notification::fake();

    $this->postJson('/api/v1/register', [
        'role' => UserRole::USER->value,
        'email' => 'resend@example.com',
    ])->assertCreated();

    $this->postJson('/api/v1/otp/register/resend', [
        'email' => 'resend@example.com',
    ])
        ->assertOk()
        ->assertJsonPath('data.verification_channel', 'email');

    $user = User::query()->where('email', 'resend@example.com')->firstOrFail();
    Notification::assertSentToTimes($user, OtpCodeNotification::class, 2);
});

test('registration otp verify must use the same guest token session', function (): void {
    Notification::fake();

    $this->postJson('/api/v1/register', [
        'role' => UserRole::USER->value,
        'email' => 'guest-bound@example.com',
    ])->assertCreated();

    $user = User::query()->where('email', 'guest-bound@example.com')->firstOrFail();
    app(OtpRepository::class)->put(
        OtpPurpose::VerifyEmail,
        OtpRepository::fingerprint('email', 'guest-bound@example.com|guest:'.hash('sha256', 'guest-test-token')),
        [
            'user_id' => $user->id,
            'hash' => OtpRepository::hashCode('333333'),
            'guest_token_hash' => hash('sha256', 'guest-test-token'),
        ],
        10
    );

    $this->withHeader('X-Guest-Token', 'different-guest-token')
        ->postJson('/api/v1/otp/register/verify', [
            'email' => 'guest-bound@example.com',
            'code' => '333333',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['code']);
});
