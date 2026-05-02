<?php

use App\Jobs\TranslateModelJob;
use App\Models\Currency;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Laravel\Passport\Passport;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(DatabaseSeeder::class);
});

test('languages index returns seeded locales', function (): void {
    $response = $this->getJson('/api/v1/languages');

    $response->assertOk()->assertJsonPath('success', true);
    expect($response->json('data'))->toBeArray()->not->toBeEmpty();
});

test('register returns token for new user', function (): void {
    $response = $this->postJson('/api/v1/register', [
        'name' => 'Jan',
        'email' => 'jan@example.com',
        'password' => 'password1',
        'password_confirmation' => 'password1',
    ]);

    $response->assertCreated()->assertJsonPath('success', true);
    expect($response->json('data.access_token'))->toBeString()->not->toBeEmpty();
    $response->assertJsonPath('data.user.role', 'user');

    $userId = $response->json('data.user.id');
    expect($userId)->toBeInt();
    $this->assertDatabaseHas('user_login_histories', [
        'user_id' => $userId,
    ]);
});

test('registration rejects admin role', function (): void {
    $this->postJson('/api/v1/register', [
        'name' => 'Rogue',
        'email' => 'rogue@example.com',
        'password' => 'password1',
        'password_confirmation' => 'password1',
        'role' => 'admin',
    ])->assertUnprocessable();
});

test('registration accepts explicit user role', function (): void {
    $this->postJson('/api/v1/register', [
        'name' => 'Pat',
        'email' => 'pat@example.com',
        'password' => 'password1',
        'password_confirmation' => 'password1',
        'role' => 'user',
    ])
        ->assertCreated()
        ->assertJsonPath('data.user.role', 'user');
});

test('login returns token for seeded user', function (): void {
    $response = $this->postJson('/api/v1/login', [
        'email' => 'user@example.com',
        'password' => 'password',
    ]);

    $response->assertOk()->assertJsonPath('success', true);
    expect($response->json('data.access_token'))->toBeString()->not->toBeEmpty();

    $user = User::query()->where('email', 'user@example.com')->firstOrFail();
    $this->assertDatabaseHas('user_login_histories', [
        'user_id' => $user->id,
    ]);
});

test('me returns profile for authenticated user', function (): void {
    $user = User::query()->where('email', 'user@example.com')->firstOrFail();
    Passport::actingAs($user);

    $this->getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonPath('data.email', 'user@example.com');
});

test('user role can access user ping', function (): void {
    $user = User::query()->where('email', 'user@example.com')->firstOrFail();
    Passport::actingAs($user);

    $this->getJson('/api/v1/user/ping')->assertOk();
});

test('user role cannot access admin routes', function (): void {
    $user = User::query()->where('email', 'user@example.com')->firstOrFail();
    Passport::actingAs($user);

    $this->getJson('/api/v1/admin/ping')->assertForbidden();
});

test('admin role can access admin ping', function (): void {
    $admin = User::query()->where('email', 'admin@example.com')->firstOrFail();
    Passport::actingAs($admin);

    $this->getJson('/api/v1/admin/ping')->assertOk();
});

test('admin role cannot access user-only routes', function (): void {
    $admin = User::query()->where('email', 'admin@example.com')->firstOrFail();
    Passport::actingAs($admin);

    $this->getJson('/api/v1/user/ping')->assertForbidden();
});

test('currencies index is public', function (): void {
    $this->getJson('/api/v1/currencies')
        ->assertOk()
        ->assertJsonPath('success', true);
});

test('authenticated user can patch preferences', function (): void {
    $user = User::query()->where('email', 'user@example.com')->firstOrFail();
    Passport::actingAs($user);

    $this->patchJson('/api/v1/preferences', [
        'locale' => 'en',
        'timezone' => 'UTC',
        'currency_code' => 'USD',
    ])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.locale', 'en')
        ->assertJsonPath('data.timezone', 'UTC');
});

test('creating a product dispatches translation job', function (): void {
    Queue::fake();

    $user = User::query()->where('email', 'user@example.com')->firstOrFail();
    Passport::actingAs($user);

    $usdId = Currency::query()->where('code', 'USD')->value('id');
    expect($usdId)->not->toBeNull();

    $this->postJson('/api/v1/user/products', [
        'name' => 'API Widget',
        'currency_id' => $usdId,
        'description' => 'Widget description',
    ])
        ->assertCreated()
        ->assertJsonPath('success', true);

    Queue::assertPushed(TranslateModelJob::class);
});

test('authenticated user can list login history', function (): void {
    $user = User::query()->where('email', 'user@example.com')->firstOrFail();

    $this->postJson('/api/v1/login', [
        'email' => 'user@example.com',
        'password' => 'password',
    ])->assertOk();

    Passport::actingAs($user);

    $response = $this->getJson('/api/v1/login-history');

    $response->assertOk()->assertJsonPath('success', true);
    expect($response->json('data'))->toBeArray()->not->toBeEmpty();
});

test('currency sync command appends api rates from frankfurter', function (): void {
    Http::fake([
        'https://api.frankfurter.app/*' => Http::response([
            'date' => '2030-06-15',
            'rates' => [
                'EUR' => 0.91,
                'SAR' => 3.76,
            ],
        ], 200),
    ]);

    config(['currency.fx_sync.enabled' => true]);

    $this->artisan('currency:sync-rates')->assertSuccessful();

    $usd = Currency::query()->where('code', 'USD')->firstOrFail();
    $eur = Currency::query()->where('code', 'EUR')->firstOrFail();

    $this->assertDatabaseHas('currency_rates', [
        'base_currency_id' => $usd->id,
        'quote_currency_id' => $eur->id,
        'source' => 'api',
    ]);
});

test('user cannot view another users product', function (): void {
    $owner = User::query()->where('email', 'user@example.com')->firstOrFail();
    $usdId = Currency::query()->where('code', 'USD')->value('id');

    $product = Product::query()->create([
        'user_id' => $owner->id,
        'currency_id' => $usdId,
        'name' => 'Owned item',
        'description' => null,
        'slug' => Product::uniqueSlugFrom('Owned item'),
        'status' => 'draft',
        'price' => null,
    ]);

    $other = User::factory()->create();

    Passport::actingAs($other);

    $this->getJson('/api/v1/user/products/'.$product->id)->assertForbidden();
});
