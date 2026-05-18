<?php

use App\Enums\ApprovalStatus;
use App\Enums\RideStatusEnum;
use App\Models\Review;
use App\Models\ReviewReplay;
use App\Models\Ride;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Laravel\Passport\Passport;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Config::set('broadcasting.default', 'log');
});

function createApprovedDriverForReplay(string $email): User
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

function createReviewForDriver(User $driver, ?User $customer = null): Review
{
    $customer ??= User::factory()->create();

    $ride = Ride::query()->create([
        'user_id' => $customer->id,
        'driver_id' => $driver->id,
        'status' => RideStatusEnum::COMPLETED_USER->value,
        'pickup_location' => 'A',
        'dropoff_location' => 'B',
        'completed_at' => now(),
    ]);

    return Review::query()->create([
        'user_id' => $customer->id,
        'ride_id' => $ride->id,
        'rating' => 5,
        'body' => 'Great service',
    ]);
}

test('driver can reply to a review on their ride', function (): void {
    $driver = createApprovedDriverForReplay('driver-replay@dev.com');
    $review = createReviewForDriver($driver);

    Passport::actingAs($driver);

    $response = $this->postJson("/api/v1/driver/reviews/{$review->id}/replays", [
        'body' => 'Thank you for your feedback!',
    ]);

    $response->assertCreated();
    $response->assertJsonPath('success', true);
    $response->assertJsonPath('data.body', 'Thank you for your feedback!');
    $response->assertJsonPath('data.review_id', $review->id);
    $response->assertJsonPath('data.user.id', $driver->id);

    expect(ReviewReplay::query()->where('review_id', $review->id)->count())->toBe(1);
});

test('driver cannot reply to a review on another drivers ride', function (): void {
    $driver = createApprovedDriverForReplay('driver-replay-owner@dev.com');
    $otherDriver = createApprovedDriverForReplay('driver-replay-other@dev.com');
    $review = createReviewForDriver($otherDriver);

    Passport::actingAs($driver);

    $this->postJson("/api/v1/driver/reviews/{$review->id}/replays", [
        'body' => 'Not allowed',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['review']);
});

test('driver can reply to a nested review replay', function (): void {
    $driver = createApprovedDriverForReplay('driver-replay-nested@dev.com');
    $review = createReviewForDriver($driver);

    Passport::actingAs($driver);

    $parent = ReviewReplay::query()->create([
        'review_id' => $review->id,
        'user_id' => $driver->id,
        'body' => 'Thanks!',
    ]);

    $this->postJson("/api/v1/driver/reviews/{$review->id}/replays", [
        'body' => 'Follow-up note',
        'parent_id' => $parent->id,
    ])
        ->assertCreated()
        ->assertJsonPath('data.parent_id', $parent->id);
});

test('driver reviews list includes replays', function (): void {
    $driver = createApprovedDriverForReplay('driver-replay-list@dev.com');
    $review = createReviewForDriver($driver);

    ReviewReplay::query()->create([
        'review_id' => $review->id,
        'user_id' => $driver->id,
        'body' => 'Reply on list',
    ]);

    Passport::actingAs($driver);

    $this->getJson('/api/v1/driver/reviews')
        ->assertSuccessful()
        ->assertJsonPath('data.0.replays.0.body', 'Reply on list')
        ->assertJsonStructure([
            'meta',
            'links',
        ]);
});

test('admin reviews list is paginated and includes replays', function (): void {
    $admin = User::factory()->admin()->create();
    $driver = createApprovedDriverForReplay('driver-replay-admin@dev.com');
    $review = createReviewForDriver($driver);

    ReviewReplay::query()->create([
        'review_id' => $review->id,
        'user_id' => $driver->id,
        'body' => 'Admin visible reply',
    ]);

    Passport::actingAs($admin);

    $this->getJson('/api/v1/admin/reviews?per_page=10')
        ->assertSuccessful()
        ->assertJsonPath('data.0.replays.0.body', 'Admin visible reply')
        ->assertJsonPath('meta.per_page', 10);
});
