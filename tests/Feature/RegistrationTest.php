<?php

use App\Enums\OtpPurpose;
use App\Enums\UserRole;
use App\Models\User;
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
    $this->seed(DatabaseSeeder::class);
});

test('user registration requires role and email and generates a visual username', function (): void {
    Notification::fake();

    $this->postJson('/api/v1/register', [
        'role' => UserRole::USER->value,
        'email' => 'customer@example.com',
    ])
        ->assertCreated()
        ->assertJsonPath('data.user.email', 'customer@example.com')
        ->assertJsonPath('data.user.role', UserRole::USER->value)
        ->assertJsonMissingPath('data.access_token')
        ->assertJsonPath('data.verification_channel', 'email')
        ->assertJsonPath('data.user.username', fn ($value): bool => is_string($value) && str_starts_with($value, 'USR-'));

    $user = User::query()->where('email', 'customer@example.com')->firstOrFail();

    expect($user->name)->toBeNull()
        ->and($user->email_verified_at)->toBeNull()
        ->and($user->username)->not->toBeNull();

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
            'car_brand',
            'car_model',
            'car_type',
            'license_plate',
            'location',
            'truck_image',
            'driving_license_image',
            'car_legal_documents',
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
        'car_brand' => 'Ford',
        'car_model' => 'F-550',
        'car_type' => 'tow truck',
        'license_plate' => 'TOW-1234',
        'location' => 'Dhaka',
        'truck_image' => UploadedFile::fake()->image('truck.png'),
        'driving_license_image' => UploadedFile::fake()->image('license.png'),
        'car_legal_documents' => UploadedFile::fake()->image('documents.png'),
    ])
        ->assertCreated()
        ->assertJsonMissingPath('data.access_token')
        ->assertJsonPath('data.user.role', UserRole::DRIVER->value)
        ->assertJsonPath('data.user.driver_profile.car_brand', 'Ford');

    $user = User::query()->where('email', 'driver@example.com')->firstOrFail();
    $profile = $user->driverProfile()->firstOrFail();

    Storage::disk('public')->assertExists($profile->truck_image_path);
    Storage::disk('public')->assertExists($profile->driving_license_image_path);
    Storage::disk('public')->assertExists($profile->car_legal_documents_path);
    Notification::assertSentTo($user, OtpCodeNotification::class);
});

test('unverified user cannot login until registration identifier is verified', function (): void {
    $user = User::factory()->create([
        'email' => 'needs-verification@example.com',
        'email_verified_at' => null,
        'password' => 'password',
    ]);

    $this->postJson('/api/v1/login', [
        'email' => 'needs-verification@example.com',
        'password' => 'password',
    ])
        ->assertForbidden()
        ->assertJsonPath('code', 'IDENTIFIER_NOT_VERIFIED');

    $code = '123456';
    app(OtpRepository::class)->put(
        OtpPurpose::VerifyEmail,
        OtpRepository::fingerprint('user', (string) $user->id),
        [
            'user_id' => $user->id,
            'hash' => OtpRepository::hashCode($code),
        ],
        10
    );

    $this->postJson('/api/v1/register/verification/otp/verify', [
        'identifier_type' => 'email',
        'identifier' => 'needs-verification@example.com',
        'code' => $code,
    ])
        ->assertOk()
        ->assertJsonPath('data.user.email_verified_at', fn ($value): bool => is_string($value) && $value !== '');

    $this->postJson('/api/v1/login', [
        'email' => 'needs-verification@example.com',
        'password' => 'password',
    ])
        ->assertOk()
        ->assertJsonPath('data.access_token', fn ($value): bool => is_string($value) && $value !== '');
});
