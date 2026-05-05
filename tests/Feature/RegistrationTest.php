<?php

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
        ->assertJsonPath('data.identifier_type', 'email')
        ->assertJsonPath('data.identifier', 'customer@example.com')
        ->assertJsonMissingPath('data.access_token')
        ->assertJsonPath('data.verification_channel', 'email');

    expect(User::query()->where('email', 'customer@example.com')->exists())->toBeFalse();

    Notification::assertSentOnDemand(OtpCodeNotification::class);
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
        ->assertJsonPath('data.identifier_type', 'email')
        ->assertJsonPath('data.identifier', 'driver@example.com');

    expect(User::query()->where('email', 'driver@example.com')->exists())->toBeFalse();

    Notification::assertSentOnDemand(OtpCodeNotification::class);
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
        ->assertUnauthorized();

    $code = '123456';
    cache()->put('registration:otp:'.OtpRepository::fingerprint('email', 'needs-verification@example.com'), [
        'hash' => OtpRepository::hashCode($code),
    ], now()->addMinutes(10));

    $this->postJson('/api/v1/otp/register/verify', [
        'email' => 'needs-verification@example.com',
        'code' => $code,
    ])
        ->assertCreated()
        ->assertJsonPath('data.user.email', 'needs-verification@example.com')
        ->assertJsonPath('data.user.email_verified_at', fn ($value): bool => is_string($value) && $value !== '')
        ->assertJsonPath('data.access_token', fn ($value): bool => is_string($value) && $value !== '');

    $user = User::query()->where('email', 'needs-verification@example.com')->firstOrFail();

    expect($user->username)->not->toBeNull();
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

    Notification::assertSentOnDemand(OtpCodeNotification::class, 2);
});
