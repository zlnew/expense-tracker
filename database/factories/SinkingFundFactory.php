<?php

namespace Database\Factories;

use App\Models\Balance;
use App\Models\SinkingFund;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SinkingFund>
 */
class SinkingFundFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'target_amount' => 400_000,
            'cadence' => 'cycle',
            'contribution_amount' => null,
            'category_id' => null,
            'from_balance_id' => Balance::factory(),
            'next_due' => null,
            'due_interval_months' => 1,
            'notes' => null,
        ];
    }
}
