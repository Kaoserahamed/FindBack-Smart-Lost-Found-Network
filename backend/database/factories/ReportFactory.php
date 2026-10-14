<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Community;
use App\Models\Report;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Report>
 */
class ReportFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'user_id' => User::factory(),
            'community_id' => Community::factory(),
            'category_id' => Category::factory(),
            'type' => 'lost',
            'title' => ucfirst(fake()->words(3, true)),
            'description' => fake()->sentence(12),
            'color' => fake()->safeColorName(),
            'brand' => fake()->company(),
            'event_at' => now()->subHours(2),
            'location_name' => fake()->streetName(),
            'latitude' => fake()->latitude(24.8, 25.0),
            'longitude' => fake()->longitude(91.7, 91.9),
            'status' => 'open',
            'expires_at' => now()->addDays(30),
        ];
    }

    /**
     * Indicate that the report is a "lost" report.
     */
    public function lost(): static
    {
        return $this->state(fn (array $attributes) => ['type' => 'lost']);
    }

    /**
     * Indicate that the report is a "found" report.
     */
    public function found(): static
    {
        return $this->state(fn (array $attributes) => ['type' => 'found']);
    }
}
