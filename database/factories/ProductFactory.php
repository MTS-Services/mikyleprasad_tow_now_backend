<?php

namespace Database\Factories;

use App\Models\Currency;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->words(3, true);

        return [
            'user_id' => User::factory(),
            'currency_id' => Currency::query()->where('code', 'USD')->value('id') ?? 1,
            'name' => $name,
            'description' => fake()->optional()->paragraph(),
            'slug' => Product::uniqueSlugFrom($name),
            'status' => 'draft',
            'price' => fake()->optional()->randomFloat(4, 1, 500),
        ];
    }
}
