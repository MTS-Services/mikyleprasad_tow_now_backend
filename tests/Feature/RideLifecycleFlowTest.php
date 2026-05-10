<?php

use App\Enums\ApprovalStatus;
use App\Enums\RideStatusEnum;
use App\Models\Ride;
use App\Models\RideHistory;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Laravel\Passport\Passport;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Config::set('broadcasting.default', 'log');
});

function createApprovedDriver(string $email): User
{
    $driver = User::factory()->driver()->create([
        'email' => $email,
        'is_suspended' => false,
    ]);

    $driver->forceFill([
        'approval_status' => ApprovalStatus::APPROVED,
    ])->save();

    return $driver;
}

test('user cannot create duplicate pending request to same driver', function (): void {
    $user = User::factory()->create();
    $driver = createApprovedDriver('driver-dup@dev.com');

    Passport::actingAs($user);

    $payload = [
        'driver_id' => $driver->id,
        'pickup_location' => 'A',
        'dropoff_location' => 'B',
    ];

    $this->postJson('/api/v1/user/rides', $payload)->assertCreated();

    $this->postJson('/api/v1/user/rides', $payload)
        ->assertStatus(422)
        ->assertJsonPath('success', false);
});

test('user with active ride cannot create new request', function (): void {
    $user = User::factory()->create();
    $driverOne = createApprovedDriver('driver-active-1@dev.com');
    $driverTwo = createApprovedDriver('driver-active-2@dev.com');

    Ride::query()->create([
        'user_id' => $user->id,
        'driver_id' => $driverOne->id,
        'status' => RideStatusEnum::ACTIVE->value,
        'pickup_location' => 'A',
        'dropoff_location' => 'B',
        'accepted_at' => now(),
    ]);

    Passport::actingAs($user);

    $this->postJson('/api/v1/user/rides', [
        'driver_id' => $driverTwo->id,
        'pickup_location' => 'X',
        'dropoff_location' => 'Y',
    ])
        ->assertStatus(422)
        ->assertJsonPath('success', false);
});

test('driver with active ride is unavailable for new requests', function (): void {
    $userOne = User::factory()->create();
    $userTwo = User::factory()->create();
    $driver = createApprovedDriver('driver-busy@dev.com');

    Ride::query()->create([
        'user_id' => $userOne->id,
        'driver_id' => $driver->id,
        'status' => RideStatusEnum::ACTIVE->value,
        'pickup_location' => 'A',
        'dropoff_location' => 'B',
        'accepted_at' => now(),
    ]);

    Passport::actingAs($userTwo);

    $this->postJson('/api/v1/user/rides', [
        'driver_id' => $driver->id,
        'pickup_location' => 'X',
        'dropoff_location' => 'Y',
    ])
        ->assertStatus(422)
        ->assertJsonPath('success', false);
});

test('accepting one pending ride auto system-cancels competing pending rides', function (): void {
    $user = User::factory()->create();
    $driverOne = createApprovedDriver('driver-auto-cancel-1@dev.com');
    $driverTwo = createApprovedDriver('driver-auto-cancel-2@dev.com');

    Passport::actingAs($user);
    $this->postJson('/api/v1/user/rides', [
        'driver_id' => $driverOne->id,
        'pickup_location' => 'A',
        'dropoff_location' => 'B',
    ])->assertCreated();

    $this->postJson('/api/v1/user/rides', [
        'driver_id' => $driverTwo->id,
        'pickup_location' => 'C',
        'dropoff_location' => 'D',
    ])->assertCreated();

    $acceptedRide = Ride::query()
        ->where('user_id', $user->id)
        ->where('driver_id', $driverOne->id)
        ->firstOrFail();

    Passport::actingAs($driverOne);
    $this->postJson("/api/v1/driver/rides/{$acceptedRide->id}/accept", [
        'eta_minutes' => 15,
    ])->assertOk();

    $competingRide = Ride::query()
        ->where('user_id', $user->id)
        ->where('driver_id', $driverTwo->id)
        ->firstOrFail();

    expect($competingRide->status)->toBe(RideStatusEnum::SYSTEM_CANCELLED);
});

test('system-cancelled rides are hidden from user ride details', function (): void {
    $user = User::factory()->create();
    $driver = createApprovedDriver('driver-hidden@dev.com');

    $ride = Ride::query()->create([
        'user_id' => $user->id,
        'driver_id' => $driver->id,
        'status' => RideStatusEnum::SYSTEM_CANCELLED->value,
        'pickup_location' => 'A',
        'dropoff_location' => 'B',
    ]);

    Passport::actingAs($user);

    $this->getJson("/api/v1/user/rides/{$ride->id}")
        ->assertNotFound();
});

test('pending ride expires via scheduler command after timeout and becomes immutable', function (): void {
    config(['rides.request_expire_minutes' => 10]);

    $user = User::factory()->create();
    $driver = createApprovedDriver('driver-expire@dev.com');

    Passport::actingAs($user);
    $this->postJson('/api/v1/user/rides', [
        'driver_id' => $driver->id,
        'pickup_location' => 'A',
        'dropoff_location' => 'B',
    ])->assertCreated();

    $ride = Ride::query()
        ->where('user_id', $user->id)
        ->where('driver_id', $driver->id)
        ->latest('id')
        ->firstOrFail();

    $ride->forceFill([
        'expired_at' => now()->subMinute(),
    ])->save();

    $this->artisan('rides:expire-pending')->assertSuccessful();

    $ride->refresh();
    expect($ride->status)->toBe(RideStatusEnum::EXPIRED);

    Passport::actingAs($driver);
    $this->postJson("/api/v1/driver/rides/{$ride->id}/accept", [
        'eta_minutes' => 10,
    ])->assertStatus(422);
});

test('eta update requires reason and logs estimated time history', function (): void {
    $user = User::factory()->create();
    $driver = createApprovedDriver('driver-eta@dev.com');

    Passport::actingAs($user);
    $this->postJson('/api/v1/user/rides', [
        'driver_id' => $driver->id,
        'pickup_location' => 'A',
        'dropoff_location' => 'B',
    ])->assertCreated();

    $ride = Ride::query()
        ->where('user_id', $user->id)
        ->where('driver_id', $driver->id)
        ->latest('id')
        ->firstOrFail();

    Passport::actingAs($driver);
    $this->postJson("/api/v1/driver/rides/{$ride->id}/accept", [
        'eta_minutes' => 20,
    ])->assertOk();

    $this->postJson("/api/v1/driver/rides/{$ride->id}/eta", [
        'eta_minutes' => 30,
    ])->assertUnprocessable();

    $this->postJson("/api/v1/driver/rides/{$ride->id}/eta", [
        'eta_minutes' => 30,
        'reason' => 'Traffic jam',
    ])->assertOk();

    expect(
        RideHistory::query()
            ->where('ride_id', $ride->id)
            ->where('type', 'estimated_time')
            ->count()
    )->toBe(2);
});

test('active ride cannot be downgraded back to pending', function (): void {
    $user = User::factory()->create();
    $driver = createApprovedDriver('driver-no-downgrade@dev.com');

    Passport::actingAs($user);
    $this->postJson('/api/v1/user/rides', [
        'driver_id' => $driver->id,
        'pickup_location' => 'A',
        'dropoff_location' => 'B',
    ])->assertCreated();

    $ride = Ride::query()
        ->where('user_id', $user->id)
        ->where('driver_id', $driver->id)
        ->latest('id')
        ->firstOrFail();

    Passport::actingAs($driver);
    $this->postJson("/api/v1/driver/rides/{$ride->id}/accept", [
        'eta_minutes' => 12,
    ])->assertOk();

    $this->postJson("/api/v1/driver/rides/{$ride->id}/accept", [
        'eta_minutes' => 15,
    ])->assertStatus(422);
});

test('ride request creates in-app notifications for rider and driver', function (): void {
    $user = User::factory()->create();
    $driver = createApprovedDriver('driver-notify@dev.com');

    Passport::actingAs($user);
    $this->postJson('/api/v1/user/rides', [
        'driver_id' => $driver->id,
        'pickup_location' => 'A',
        'dropoff_location' => 'B',
    ])->assertCreated();

    $ride = Ride::query()->where('user_id', $user->id)->where('driver_id', $driver->id)->firstOrFail();

    expect(UserNotification::query()->where('user_id', $user->id)->where('type', 'ride.request_sent')->exists())->toBeTrue();
    expect(UserNotification::query()->where('user_id', $driver->id)->where('type', 'ride.requested')->exists())->toBeTrue();
});

test('active ride cannot be completed before arrived', function (): void {
    $user = User::factory()->create();
    $driver = createApprovedDriver('driver-complete-gate@dev.com');

    Passport::actingAs($user);
    $this->postJson('/api/v1/user/rides', [
        'driver_id' => $driver->id,
        'pickup_location' => 'A',
        'dropoff_location' => 'B',
    ])->assertCreated();

    $ride = Ride::query()->where('user_id', $user->id)->firstOrFail();

    Passport::actingAs($driver);
    $this->postJson("/api/v1/driver/rides/{$ride->id}/accept", ['eta_minutes' => 10])->assertOk();

    Passport::actingAs($user);
    $this->postJson("/api/v1/user/rides/{$ride->id}/complete")->assertStatus(422);
});

test('arrived ride can be completed and admins receive notification', function (): void {
    User::factory()->admin()->create(['email' => 'admin-ride@dev.com']);

    $user = User::factory()->create();
    $driver = createApprovedDriver('driver-arrived-complete@dev.com');

    Passport::actingAs($user);
    $this->postJson('/api/v1/user/rides', [
        'driver_id' => $driver->id,
        'pickup_location' => 'A',
        'dropoff_location' => 'B',
    ])->assertCreated();

    $ride = Ride::query()->where('user_id', $user->id)->firstOrFail();

    Passport::actingAs($driver);
    $this->postJson("/api/v1/driver/rides/{$ride->id}/accept", ['eta_minutes' => 10])->assertOk();
    $this->postJson("/api/v1/driver/rides/{$ride->id}/arrived")->assertOk();

    Passport::actingAs($user);
    $this->postJson("/api/v1/user/rides/{$ride->id}/complete")->assertOk();

    $ride->refresh();
    expect($ride->status)->toBe(RideStatusEnum::COMPLETED_USER);
    expect($ride->total_ride_minutes)->not->toBeNull();

    $admin = User::query()->where('email', 'admin-ride@dev.com')->firstOrFail();
    expect(UserNotification::query()->where('user_id', $admin->id)->where('type', 'ride.completed_admin')->exists())->toBeTrue();
});

test('user can dismiss own notification', function (): void {
    $user = User::factory()->create();

    $notification = UserNotification::factory()->create([
        'user_id' => $user->id,
    ]);

    Passport::actingAs($user);
    $this->deleteJson("/api/v1/notifications/{$notification->id}")->assertOk();

    expect(UserNotification::query()->withTrashed()->find($notification->id)?->trashed())->toBeTrue();
});
