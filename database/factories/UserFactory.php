<?php

namespace Database\Factories;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Fortify\Fortify;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->optional()->e164PhoneNumber(),
            'locale' => 'en',
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'role' => UserRole::USER,
            'remember_token' => Str::random(10),
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => UserRole::ADMIN,
        ]);
    }

    public function driver(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => UserRole::DRIVER,
        ]);
    }

    /**
     * Known Base32 secret for PragmaRX Google2FA (tests only).
     */
    public function withConfirmedTwoFactor(?string $plainSecret = null): static
    {
        $secret = $plainSecret ?? 'JBSWY3DPEHPK3PXP';

        return $this->state(fn (array $attributes) => [
            'two_factor_secret' => Fortify::currentEncrypter()->encrypt($secret),
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => null,
        ]);
    }
}
