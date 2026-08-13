<?php

namespace App\Support;

use App\Enums\CategoryType;

/**
 * Canonical default category set, cloned per user on registration and for
 * backfilling. Single source of truth — do not hardcode the list elsewhere.
 */
class DefaultCategories
{
    /**
     * @return array<int, array{type: CategoryType, name: string}>
     */
    public static function all(): array
    {
        $incomes = [
            'Savings',
            'Paycheck',
            'Bonus',
            'Interest',
            'Other',
        ];

        $expenses = [
            'Food',
            'Gifts',
            'Health',
            'Home',
            'Transportation',
            'Personal',
            'Pets',
            'Utilities',
            'Travel',
            'Maintenance',
            'Taxes',
        ];

        return [
            ...array_map(
                fn (string $name) => ['type' => CategoryType::INCOME, 'name' => $name],
                $incomes,
            ),
            ...array_map(
                fn (string $name) => ['type' => CategoryType::EXPENSE, 'name' => $name],
                $expenses,
            ),
        ];
    }
}
