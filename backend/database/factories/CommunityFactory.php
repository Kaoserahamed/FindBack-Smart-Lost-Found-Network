<?php

namespace Database\Factories;

use App\Models\Community;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Community>
 */
class CommunityFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->company().' Campus';

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'type' => fake()->randomElement(['university', 'transport', 'shopping_mall', 'workplace', 'residential', 'other']),
            'description' => fake()->sentence(),
            'address' => fake()->address(),
            'latitude' => fake()->latitude(),
            'longitude' => fake()->longitude(),
            'status' => 'approved',
        ];
    }

    /**
     * Indicate that the community is a university.
     */
    public function university(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'university',
        ]);
    }
}
