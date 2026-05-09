<?php

namespace Database\Seeders;

use App\Enums\AccountStatus;
use App\Enums\ApprovalStatus;
use App\Enums\UserRole;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Laravel\Passport\ClientRepository;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $clients = app(ClientRepository::class);

        try {
            $clients->personalAccessClient(config('auth.guards.api.provider', 'users'));
        } catch (\RuntimeException) {
            $clients->createPersonalAccessGrantClient(
                (string) config('app.name') . ' Personal Access Client',
                config('auth.guards.api.provider', 'users')
            );
        }

        User::query()->firstOrCreate(
            ['email' => 'm.alexpersad@gmail.com'],
            [
                'name' => 'Alex Persad',
                'phone' => null,
                'locale' => 'en',
                'password' => Hash::make('password'),
                'role' => UserRole::ADMIN,
                'email_verified_at' => now(),
            ]
        );
        User::query()->firstOrCreate(
            ['email' => 'mahfuz.maktech@gmail.com'],
            [
                'name' => 'Mahfuz Ahmed',
                'phone' => null,
                'locale' => 'en',
                'password' => Hash::make('password'),
                'role' => UserRole::ADMIN,
                'email_verified_at' => now(),
            ]
        );
        User::query()->firstOrCreate(
            ['email' => 'monirul.maktech@gmail.com'],
            [
                'name' => 'Monirul Islam',
                'phone' => null,
                'locale' => 'en',
                'password' => Hash::make('password'),
                'role' => UserRole::ADMIN,
                'email_verified_at' => now(),
            ]
        );
        User::query()->firstOrCreate(
            ['email' => 'akhtaruzzamansumon7@gmail.com'],
            [
                'name' => 'Akhtaruzzaman Sumon',
                'phone' => null,
                'locale' => 'en',
                'password' => Hash::make('password'),
                'role' => UserRole::ADMIN,
                'email_verified_at' => now(),
            ]
        );
        User::query()->firstOrCreate(
            ['email' => 'admin@dev.com'],
            [
                'name' => 'Demo Admin',
                'password' => Hash::make('password'),
                'role' => UserRole::ADMIN,
                'email_verified_at' => now(),
                'address' => '123 Main St, Anytown, USA',
                'bio' => 'I am a demo admin',
            ]
        );
        User::query()->firstOrCreate(
            ['email' => 'driver@dev.com'],
            [
                'name' => 'Demo Driver',
                'password' => Hash::make('password'),
                'role' => UserRole::DRIVER,
                'email_verified_at' => now(),
                'address' => '123 Main St, Anytown, USA',
                'bio' => 'I am a demo driver',
            ]
        );
        User::query()->firstOrCreate(
            ['email' => 'user@dev.com'],
            [
                'name' => 'Demo User',
                'password' => Hash::make('password'),
                'role' => UserRole::USER,
                'email_verified_at' => now(),
                'address' => '123 Main St, Anytown, USA',
                'bio' => 'I am a demo user',
            ]
        );

        $this->createDrivers();
        $this->createUsers();
    }

    protected function createDrivers(int $count = 300): void
    {
        for ($i = 0; $i < $count; $i++) {
            $driver = User::query()->firstOrCreate(
                ['email' => 'driver' . $i . '@dev.com'],
                [
                    'name' => 'Driver ' . $i,
                    'password' => Hash::make('password'),
                    'role' => UserRole::DRIVER,
                    'status' => random_int(0, 1) ? AccountStatus::ACTIVE : AccountStatus::INACTIVE,
                    'is_suspended' => random_int(0, 1) ? true : false,
                    'approval_status' => random_int(0, 1) ? ApprovalStatus::APPROVED : ApprovalStatus::PENDING,
                    'email_verified_at' => now(),
                    'address' => '123 Main St, Anytown, USA' . $i,
                    'bio' => 'I am a demo driver ' . $i,
                    'is_featured' => random_int(0, 1) ? true : false,
                ]
            );
            Vehicle::query()->firstOrCreate(
                ['user_id' => $driver->id],
                [
                    'name' => 'Vehicle ' . $i,
                    'description' => 'Vehicle description ' . $i,
                    'brand' => 'Brand ' . $i,
                    'model' => 'Model ' . $i,
                    'capacity' => random_int(1000, 10000),
                    'license_plate' => 'ABC123' . $i,
                    'truck_image' => 'https://placehold.co/800?text=' . urlencode($driver->name) . '&font=roboto',
                    'driving_license_image' => 'https://placehold.co/800?text=' . urlencode($driver->name) . '&font=roboto',
                    'legal_documents' => 'https://placehold.co/800?text=' . urlencode($driver->name) . '&font=roboto',
                    'insurance_status' => random_int(0, 1) ? true : false,
                ]
            );

            // show Info in console
            $this->command->info('Created driver ' . $driver->name);
        }
    }

    protected function createUsers(int $count = 50): void
    {
        for ($i = 0; $i < $count; $i++) {
            $user = User::query()->firstOrCreate(
                ['email' => 'user' . $i . '@dev.com'],
                [
                    'name' => 'User ' . $i,
                    'password' => Hash::make('password'),
                    'role' => UserRole::USER,
                    'status' => random_int(0, 1) ? AccountStatus::ACTIVE : AccountStatus::INACTIVE,
                    'email_verified_at' => now(),
                    'address' => '123 Main St, Anytown, USA ' . $i,
                    'bio' => 'I am a demo user ' . $i,
                ]
            );

            // show Info in console
            $this->command->info('Created user ' . $user->name);
        }
    }
}
