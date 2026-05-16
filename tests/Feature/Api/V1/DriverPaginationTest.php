<?php

use App\Enums\ApprovalStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\Passport;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Cache::flush();
});

/**
 * @return list<int>
 */
function driverIdsFromJsonResponse(TestResponse $response): array
{
    $data = $response->json('data');

    return collect(is_array($data) ? $data : [])
        ->pluck('id')
        ->map(fn ($id): int => (int) $id)
        ->values()
        ->all();
}

function actingAsAdmin(): User
{
    $admin = User::factory()->admin()->create();
    Passport::actingAs($admin);

    return $admin;
}

test('public tab all returns only approved non-suspended drivers', function (): void {
    $visible = User::factory()->driver()->create([
        'approval_status' => ApprovalStatus::APPROVED,
        'is_suspended' => false,
        'is_featured' => false,
    ]);

    $pending = User::factory()->driver()->create([
        'approval_status' => ApprovalStatus::PENDING,
        'is_suspended' => false,
    ]);

    $rejected = User::factory()->driver()->create([
        'approval_status' => ApprovalStatus::REJECTED,
        'is_suspended' => false,
    ]);

    $suspended = User::factory()->driver()->create([
        'approval_status' => ApprovalStatus::APPROVED,
        'is_suspended' => true,
    ]);

    $response = $this->getJson('/api/v1/drivers/find?tab=all&sort=random&seed=test-seed');

    $response->assertOk()->assertJsonPath('success', true);

    $ids = driverIdsFromJsonResponse($response);

    expect($ids)->toContain($visible->id);
    expect($ids)->not->toContain($pending->id, $rejected->id, $suspended->id);
});

test('public tab featured_drivers returns only featured approved drivers', function (): void {
    $featured = User::factory()->driver()->create([
        'approval_status' => ApprovalStatus::APPROVED,
        'is_suspended' => false,
        'is_featured' => true,
    ]);

    $nonFeatured = User::factory()->driver()->create([
        'approval_status' => ApprovalStatus::APPROVED,
        'is_suspended' => false,
        'is_featured' => false,
    ]);

    $response = $this->getJson('/api/v1/drivers/find?tab=featured_drivers&sort=random&seed=featured-seed');

    $response->assertOk();

    $ids = driverIdsFromJsonResponse($response);

    expect($ids)->toContain($featured->id);
    expect($ids)->not->toContain($nonFeatured->id);
});

test('public seeded random pagination returns stable disjoint pages', function (): void {
    foreach (range(1, 5) as $i) {
        User::factory()->driver()->create([
            'approval_status' => ApprovalStatus::APPROVED,
            'is_suspended' => false,
            'name' => "Driver {$i}",
        ]);
    }

    $seed = 'stable-pagination-seed';

    $pageOne = driverIdsFromJsonResponse(
        $this->getJson("/api/v1/drivers/find?tab=all&per_page=2&page=1&sort=random&seed={$seed}")
    );

    $pageTwo = driverIdsFromJsonResponse(
        $this->getJson("/api/v1/drivers/find?tab=all&per_page=2&page=2&sort=random&seed={$seed}")
    );

    expect($pageOne)->toHaveCount(2);
    expect($pageTwo)->toHaveCount(2);
    expect(array_intersect($pageOne, $pageTwo))->toBeEmpty();

    $repeatPageOne = driverIdsFromJsonResponse(
        $this->getJson("/api/v1/drivers/find?tab=all&per_page=2&page=1&sort=random&seed={$seed}")
    );

    expect($repeatPageOne)->toBe($pageOne);
});

test('admin pending tab returns only pending non-suspended drivers', function (): void {
    actingAsAdmin();

    $pending = User::factory()->driver()->create([
        'approval_status' => ApprovalStatus::PENDING,
        'is_suspended' => false,
    ]);

    $approved = User::factory()->driver()->create([
        'approval_status' => ApprovalStatus::APPROVED,
        'is_suspended' => false,
    ]);

    $response = $this->getJson('/api/v1/admin/drivers?tab=pending&sort=latest');

    $response->assertOk()->assertJsonPath('success', true);

    $ids = driverIdsFromJsonResponse($response);

    expect($ids)->toContain($pending->id);
    expect($ids)->not->toContain($approved->id);
});

test('admin all tab returns approved non-suspended drivers only', function (): void {
    actingAsAdmin();

    $active = User::factory()->driver()->create([
        'approval_status' => ApprovalStatus::APPROVED,
        'is_suspended' => false,
    ]);

    $pending = User::factory()->driver()->create([
        'approval_status' => ApprovalStatus::PENDING,
        'is_suspended' => false,
    ]);

    $rejected = User::factory()->driver()->create([
        'approval_status' => ApprovalStatus::REJECTED,
        'is_suspended' => false,
    ]);

    $suspended = User::factory()->driver()->create([
        'approval_status' => ApprovalStatus::APPROVED,
        'is_suspended' => true,
    ]);

    $response = $this->getJson('/api/v1/admin/drivers?tab=all&sort=latest');

    $response->assertOk();

    $ids = driverIdsFromJsonResponse($response);

    expect($ids)->toContain($active->id);
    expect($ids)->not->toContain($pending->id, $rejected->id, $suspended->id);
});

test('admin featured tab returns featured approved drivers', function (): void {
    actingAsAdmin();

    $featured = User::factory()->driver()->create([
        'approval_status' => ApprovalStatus::APPROVED,
        'is_suspended' => false,
        'is_featured' => true,
    ]);

    $nonFeatured = User::factory()->driver()->create([
        'approval_status' => ApprovalStatus::APPROVED,
        'is_suspended' => false,
        'is_featured' => false,
    ]);

    $response = $this->getJson('/api/v1/admin/drivers?tab=featured_drivers&sort=latest');

    $ids = driverIdsFromJsonResponse($response);

    expect($ids)->toContain($featured->id);
    expect($ids)->not->toContain($nonFeatured->id);
});

test('admin suspended tab returns suspended drivers', function (): void {
    actingAsAdmin();

    $suspended = User::factory()->driver()->create([
        'approval_status' => ApprovalStatus::APPROVED,
        'is_suspended' => true,
    ]);

    $active = User::factory()->driver()->create([
        'approval_status' => ApprovalStatus::APPROVED,
        'is_suspended' => false,
    ]);

    $response = $this->getJson('/api/v1/admin/drivers?tab=suspended&sort=latest');

    $ids = driverIdsFromJsonResponse($response);

    expect($ids)->toContain($suspended->id);
    expect($ids)->not->toContain($active->id);
});

test('admin rejected tab returns rejected non-suspended drivers', function (): void {
    actingAsAdmin();

    $rejected = User::factory()->driver()->create([
        'approval_status' => ApprovalStatus::REJECTED,
        'is_suspended' => false,
    ]);

    $active = User::factory()->driver()->create([
        'approval_status' => ApprovalStatus::APPROVED,
        'is_suspended' => false,
    ]);

    $response = $this->getJson('/api/v1/admin/drivers?tab=rejected&sort=latest');

    $ids = driverIdsFromJsonResponse($response);

    expect($ids)->toContain($rejected->id);
    expect($ids)->not->toContain($active->id);
});

test('admin sort latest returns newest driver first', function (): void {
    actingAsAdmin();

    $older = User::factory()->driver()->create([
        'approval_status' => ApprovalStatus::PENDING,
        'is_suspended' => false,
        'created_at' => now()->subDay(),
    ]);

    $newer = User::factory()->driver()->create([
        'approval_status' => ApprovalStatus::PENDING,
        'is_suspended' => false,
        'created_at' => now(),
    ]);

    $response = $this->getJson('/api/v1/admin/drivers?tab=pending&sort=latest');

    $ids = driverIdsFromJsonResponse($response);

    expect($ids[0])->toBe($newer->id);
    expect($ids)->toContain($older->id);
});
