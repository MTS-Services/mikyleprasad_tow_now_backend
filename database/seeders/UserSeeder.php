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
                (string) config('app.name').' Personal Access Client',
                config('auth.guards.api.provider', 'users')
            );
        }

        User::query()->firstOrCreate(
            ['email' => 'admin@dev.com'],
            [
                'name' => 'Admin',
                'phone' => null,
                'locale' => 'en',
                'password' => Hash::make('admin@dev.com'),
                'role' => UserRole::ADMIN,
                'email_verified_at' => now(),
            ]
        );

        User::query()->firstOrCreate(
            ['email' => 'user@dev.com'],
            [
                'name' => 'Demo User',
                'phone' => null,
                'locale' => 'en',
                'password' => Hash::make('user@dev.com'),
                'role' => UserRole::USER,
                'email_verified_at' => now(),
            ]
        );
    }
}
