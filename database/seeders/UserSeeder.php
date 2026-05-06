<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
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
    }
}
