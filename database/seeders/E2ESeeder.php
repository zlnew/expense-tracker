<?php

namespace Database\Seeders;

use App\Enums\CategoryType;
use App\Models\Balance;
use App\Models\Budget;
use App\Models\BudgetItem;
use App\Models\Category;
use App\Models\FundContribution;
use App\Models\RecurringTransaction;
use App\Models\SinkingFund;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Deterministic fixture data for the Playwright e2e suite.
 *
 * NOT wired into DatabaseSeeder — invoked explicitly by tests/e2e/scripts/
 * e2e-server.sh via `migrate:fresh --seed --seeder=E2ESeeder`. The seeded
 * user has no 2FA, so the real Fortify login flow is exercised by the specs.
 */
class E2ESeeder extends Seeder
{
    public function run(): void
    {
        $user = User::factory()->create([
            'name' => 'E2E User',
            'email' => 'e2e@et.local',
            'password' => 'password',
        ]);

        // Default per-user category set (CategorySeeder seeds for the first user).
        $this->call(CategorySeeder::class);

        $expenseCategories = Category::query()
            ->where('user_id', $user->id)
            ->where('type', CategoryType::EXPENSE)
            ->get();
        $incomeCategories = Category::query()
            ->where('user_id', $user->id)
            ->where('type', CategoryType::INCOME)
            ->get();

        $balance = Balance::factory()->for($user)->create([
            'name' => 'Kas Utama',
            'initial_amount' => 5_000_000,
            'final_amount' => 4_200_000,
            'is_primary' => true,
        ]);

        // Two budgets with real items so budget pages render content.
        $budgetOne = Budget::factory()->for($user)->create([
            'period_start' => now()->startOfMonth(),
            'period_end' => now()->endOfMonth(),
            'cutoff_day' => 25,
            'is_active' => true,
        ]);
        $budgetTwo = Budget::factory()->for($user)->create([
            'period_start' => now()->startOfMonth(),
            'period_end' => now()->endOfMonth(),
            'cutoff_day' => 1,
            'is_active' => false,
        ]);

        $budgetOneItems = $expenseCategories->take(3)->map(
            fn (Category $category) => BudgetItem::factory()
                ->for($budgetOne)
                ->for($category)
                ->create(['type' => CategoryType::EXPENSE, 'planned_amount' => 500_000])
        );

        $budgetTwoItems = $expenseCategories->slice(3, 2)->map(
            fn (Category $category) => BudgetItem::factory()
                ->for($budgetTwo)
                ->for($category)
                ->create(['type' => CategoryType::EXPENSE, 'planned_amount' => 300_000])
        );

        // Sinking fund with one contribution.
        $fund = SinkingFund::factory()->for($user)->create([
            'name' => 'Dana Darurat',
            'target_amount' => 10_000_000,
            'cadence' => 'cycle',
        ]);
        FundContribution::factory()->for($user)->for($fund, 'fund')->create([
            'type' => 'contribution',
            'amount' => 500_000,
            'date' => now()->subDays(2)->toDateString(),
        ]);

        // ~8 transactions across categories and both budget items.
        $items = $budgetOneItems->concat($budgetTwoItems);
        $allCategories = $expenseCategories->concat($incomeCategories);

        foreach (range(1, 8) as $index) {
            $item = $items->get($index % $items->count());
            $category = $item->category_id !== null && $index % 4 === 0
                ? $incomeCategories->first()
                : $allCategories->get($index % $allCategories->count());

            Transaction::factory()
                ->for($user)
                ->for($balance)
                ->for($item->budget)
                ->for($item, 'budgetItem')
                ->for($category)
                ->create([
                    'type' => $category->type,
                    'date' => now()->subDays($index)->toDateString(),
                    'amount' => match (true) {
                        $index % 4 === 0 => 750_000,
                        $index % 3 === 0 => 120_000,
                        default => 45_000 + ($index * 7_000),
                    },
                    'description' => "Transaksi e2e #{$index}",
                ]);
        }

        // One recurring transaction so the recurring page has content.
        RecurringTransaction::factory()->for($user)->create([
            'balance_id' => $balance->id,
            'category_id' => $expenseCategories->first()?->id,
            'type' => CategoryType::EXPENSE,
            'amount' => 150_000,
            'description' => 'Langganan bulanan',
            'frequency' => 'monthly',
            'start_date' => now()->startOfMonth()->toDateString(),
            'next_run_date' => now()->addMonth()->startOfMonth()->toDateString(),
        ]);
    }
}
