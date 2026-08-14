<?php

namespace Database\Factories;

use App\Models\FundContribution;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FundContribution>
 */
class FundContributionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => 'contribution',
            'amount' => fake()->numberBetween(10_000, 200_000),
            'date' => now()->toDateString(),
            'transaction_id' => null,
            'description' => null,
        ];
    }
}
