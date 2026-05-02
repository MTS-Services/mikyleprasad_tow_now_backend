<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
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
            ['email' => 'admin@example.com'],
            [
                'name' => 'Admin',
                'phone' => null,
                'locale' => 'en',
                'password' => 'password',
                'role' => UserRole::ADMIN,
                'email_verified_at' => now(),
            ]
        );

        User::query()->firstOrCreate(
            ['email' => 'user@example.com'],
            [
                'name' => 'Demo User',
                'phone' => null,
                'locale' => 'en',
                'password' => 'password',
                'role' => UserRole::USER,
                'email_verified_at' => now(),
            ]
        );
    }
}
