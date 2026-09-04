<?php

namespace App\Queries;

use App\Enums\CategoryType;
use App\Models\Budget;
use App\Models\BudgetItem;
use App\Models\User;
use App\Support\BudgetActuals;
use App\Support\BudgetCycle;

class GetExpenseBreakdown extends Query
{
    public readonly User $user;

    private readonly ?Budget $activeBudget;

    public function __construct(User|int $user)
    {
        $this->user = $user instanceof User
            ? $user
            : User::query()->findOrFail($user);

        $this->activeBudget = Budget::query()
            ->where('user_id', $this->user->id)
            ->where('is_active', true)
            ->first();
    }

    public function handle(): array
    {
        if (! $this->activeBudget) {
            return [];
        }

        [$start, $end] = BudgetCycle::currentCycleRange($this->activeBudget);

        $budgetItems = BudgetItem::query()
            ->with('category')
            ->where('budget_id', $this->activeBudget->id)
            ->where('type', CategoryType::EXPENSE)
            ->get();

        // Envelope-aware actuals: real expenses + fund set-asides, minus
        // budget-exempt fund payouts (BudgetActuals).
        $actuals = BudgetActuals::perItem($this->user, $this->activeBudget, $start, $end);

        // Total over the expense items actually returned — perItem may also
        // carry income item keys (deliberate; see BudgetActuals::perItem).
        $totalExpense = collect($budgetItems)
            ->sum(fn ($item) => $actuals[$item->id] ?? 0);

        if ($totalExpense <= 0) {
            return [];
        }

        return $budgetItems
            ->map(function ($budgetItem) use ($actuals, $totalExpense) {
                $amount = $actuals[$budgetItem->id] ?? 0;
                $percentage = round(($amount / $totalExpense) * 100, 2);

                return [
                    'category' => $budgetItem->category->name,
                    'amount' => $amount,
                    'percentage' => $percentage,
                ];
            })
            ->filter(fn ($item) => $item['amount'] > 0)
            ->sortByDesc(fn ($item) => $item['amount'])
            ->values()
            ->toArray();
    }
}
