<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserNotification>
 */
class UserNotificationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'sender_id' => null,
            'type' => fake()->randomElement(['order.updated', 'message.received', 'system.alert']),
            'title' => fake()->sentence(4),
            'body' => fake()->optional()->paragraph(),
            'data' => ['ref' => fake()->uuid()],
            'action_url' => fake()->optional()->url(),
            'read_at' => null,
        ];
    }

    public function read(): static
    {
        return $this->state(fn (array $attributes) => [
            'read_at' => now(),
        ]);
    }
}
