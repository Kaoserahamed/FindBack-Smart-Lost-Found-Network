<?php

namespace Database\Factories;

use App\Models\Claim;
use App\Models\Report;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Claim>
 */
class ClaimFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'report_id' => Report::factory()->found(),
            'claimant_id' => User::factory(),
            'answers' => [
                'q1' => fake()->sentence(),
                'q2' => fake()->word(),
            ],
            'evidence_path' => null,
            'status' => 'submitted',
            'reviewed_by' => null,
            'reviewed_at' => null,
            'decision_reason' => null,
        ];
    }

    /**
     * Indicate that the claim is approved.
     */
    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'approved',
            'reviewed_at' => now(),
            'decision_reason' => 'Verified successfully.',
        ]);
    }
}
