<?php

namespace App\Support;

use App\Enums\CategoryType;
use App\Models\Budget;
use App\Models\BudgetItem;
use App\Models\User;
use Spatie\LaravelData\DataCollection;

/**
 * YNAB-style rollover: the unused portion of a budget cycle (planned − spent)
 * carries into the next budget as extra planned amount, per category.
 */
class BudgetRollover
{
    /**
     * Find the user's most recent budget that ended before $budget started.
     */
    public static function previousBudget(Budget $budget): ?Budget
    {
        return Budget::query()
            ->where('user_id', $budget->user_id)
            ->where('id', '!=', $budget->id)
            ->where('period_end', '<', $budget->period_start)
            ->orderByDesc('period_end')
            ->first();
    }

    /**
     * Map of category_id → leftover (planned − spent, min 0) from the previous
     * budget cycle. Empty when there is no previous budget.
     *
     * @return array<int, int>
     */
    public static function leftovers(Budget $budget): array
    {
        $previous = self::previousBudget($budget);

        if (! $previous) {
            return [];
        }

        return self::budgetLeftovers($previous);
    }

    /**
     * Preview what would roll over for a brand-new budget: the user's most
     * recent ended budget's own unused amounts, keyed by category_id.
     *
     * @return array<int, int>
     */
    public static function previewForUser(User $user): array
    {
        $latest = Budget::query()
            ->where('user_id', $user->id)
            ->where('period_end', '<', now())
            ->orderByDesc('period_end')
            ->first();

        if (! $latest) {
            return [];
        }

        return self::budgetLeftovers($latest);
    }

    /**
     * Compute the unused amount per expense category of a single budget.
     *
     * @return array<int, int>
     */
    private static function budgetLeftovers(Budget $budget): array
    {
        $items = BudgetItem::query()
            ->where('budget_id', $budget->id)
            ->where('type', CategoryType::EXPENSE)
            ->get()
            ->keyBy('category_id');

        if ($items->isEmpty()) {
            return [];
        }

        // Envelope-aware actuals: set-asides count as used, payouts are
        // budget-exempt — reserved money never silently rolls over twice.
        $actuals = BudgetActuals::perItem($budget->user, $budget, $budget->period_start, $budget->period_end);

        $leftovers = [];

        foreach ($items as $categoryId => $item) {
            $actualAmount = $actuals[$item->id] ?? 0;
            $leftover = max(0, $item->planned_amount - $actualAmount);

            if ($leftover > 0) {
                $leftovers[$categoryId] = $leftover;
            }
        }

        return $leftovers;
    }

    /**
     * Apply leftovers to the submitted budget items (used by SaveBudget when
     * a new budget has carry_over enabled). Returns the same collection with
     * adjusted planned amounts.
     */
    public static function apply(Budget $budget, DataCollection $items): DataCollection
    {
        $leftovers = self::leftovers($budget);

        if ($leftovers === []) {
            return $items;
        }

        foreach ($items as $item) {
            $item->planned_amount += $leftovers[$item->category_id] ?? 0;
        }

        return $items;
    }
}
