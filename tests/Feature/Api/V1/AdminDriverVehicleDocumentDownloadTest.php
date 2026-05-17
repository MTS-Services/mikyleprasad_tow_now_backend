<?php

use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Passport\Passport;

uses(RefreshDatabase::class);

function actingAsAdminForDownload(): User
{
    $admin = User::factory()->admin()->create();
    Passport::actingAs($admin);

    return $admin;
}

test('admin can download driver vehicle document from storage', function (): void {
    Storage::fake('public');
    actingAsAdminForDownload();

    $driver = User::factory()->driver()->create();
    $storedPath = UploadedFile::fake()->image('truck.png')->store('driver-profiles/trucks', 'public');

    Vehicle::query()->create([
        'user_id' => $driver->id,
        'name' => 'Tow Truck',
        'model' => 'F-350',
        'brand' => 'Ford',
        'license_plate' => 'TEST-001',
        'truck_image' => $storedPath,
        'driving_license_image' => null,
        'legal_documents' => null,
    ]);

    $response = $this->get("/api/v1/admin/drivers/{$driver->id}/vehicle-documents/truck_image");

    $response->assertOk();
    $response->assertDownload();
});

test('non-admin cannot download driver vehicle document', function (): void {
    Storage::fake('public');
    $user = User::factory()->create();
    Passport::actingAs($user);

    $driver = User::factory()->driver()->create();
    $storedPath = UploadedFile::fake()->image('truck.png')->store('driver-profiles/trucks', 'public');

    Vehicle::query()->create([
        'user_id' => $driver->id,
        'name' => 'Tow Truck',
        'model' => 'F-350',
        'brand' => 'Ford',
        'license_plate' => 'TEST-001',
        'truck_image' => $storedPath,
    ]);

    $this->get("/api/v1/admin/drivers/{$driver->id}/vehicle-documents/truck_image")
        ->assertForbidden();
});

test('admin vehicle document download redirects for external urls', function (): void {
    actingAsAdminForDownload();

    $driver = User::factory()->driver()->create();

    Vehicle::query()->create([
        'user_id' => $driver->id,
        'name' => 'Tow Truck',
        'model' => 'F-350',
        'brand' => 'Ford',
        'license_plate' => 'TEST-001',
        'truck_image' => 'https://example.com/truck.png',
    ]);

    $this->get("/api/v1/admin/drivers/{$driver->id}/vehicle-documents/truck_image")
        ->assertRedirect('https://example.com/truck.png');
});
