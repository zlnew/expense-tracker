<?php

namespace Database\Seeders;

use App\Enums\CategoryType;
use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Incomes
        $incomes = [
            'Savings',
            'Paycheck',
            'Bonus',
            'Interest',
            'Other',
        ];
        foreach ($incomes as $inc) {
            Category::query()->updateOrCreate([
                'type' => CategoryType::INCOME,
                'name' => $inc,
            ]);
        }

        // Expenses
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
            'Debt',
            'Emergency',
            'Other',
        ];
        foreach ($expenses as $exp) {
            Category::query()->updateOrCreate([
                'type' => CategoryType::EXPENSE,
                'name' => $exp,
            ]);
        }
    }
}
