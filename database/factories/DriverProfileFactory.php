<?php

namespace Database\Factories;

use App\Models\DriverProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DriverProfile>
 */
class DriverProfileFactory extends Factory
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
            'car_brand' => fake()->company(),
            'car_model' => fake()->word(),
            'car_type' => fake()->randomElement(['flatbed', 'wheel-lift', 'integrated']),
            'license_plate' => strtoupper(fake()->bothify('???-####')),
            'location' => fake()->city(),
            'truck_image_path' => 'driver-profiles/trucks/'.fake()->uuid().'.jpg',
            'driving_license_image_path' => 'driver-profiles/licenses/'.fake()->uuid().'.jpg',
            'car_legal_documents_path' => 'driver-profiles/documents/'.fake()->uuid().'.jpg',
        ];
    }
}
