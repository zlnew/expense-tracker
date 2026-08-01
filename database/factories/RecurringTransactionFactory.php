<?php

namespace Database\Factories;

use App\Enums\CategoryType;
use App\Models\RecurringTransaction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RecurringTransaction>
 */
class RecurringTransactionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => CategoryType::EXPENSE,
            'amount' => fake()->numberBetween(10_000, 500_000),
            'description' => fake()->sentence(),
            'frequency' => 'monthly',
            'start_date' => now()->startOfMonth(),
            'end_date' => null,
            'next_run_date' => now()->toDateString(),
            'is_active' => true,
        ];
    }
}
