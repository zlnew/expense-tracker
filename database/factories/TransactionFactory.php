<?php

namespace Database\Factories;

use App\Enums\CategoryType;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Transaction>
 */
class TransactionFactory extends Factory
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
            'date' => now(),
            'amount' => fake()->numberBetween(1_000, 100_000),
            'description' => null,
            'transfer_group_id' => null,
        ];
    }
}
