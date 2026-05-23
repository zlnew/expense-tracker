<?php

namespace App\Actions;

use App\Enums\CategoryType;
use App\Models\Budget;
use App\Models\BudgetItem;
use App\Models\Transaction;
use App\Models\User;

class GetExpenseBreakdown extends Action
{
    public readonly User $user;

    public function __construct(User|int $user)
    {
        $this->user = $user instanceof User
            ? $user
            : User::query()->findOrFail($user);
    }

    public function handle(): array
    {
        $activeBudget = Budget::query()
            ->where('user_id', $this->user->id)
            ->where('is_active', true)
            ->first();

        if (! $activeBudget) {
            return [];
        }

        $now = now();

        $budgetItems = BudgetItem::query()
            ->with('category')
            ->where('budget_id', $activeBudget->id)
            ->where('type', CategoryType::EXPENSE)
            ->get();

        $expenses = Transaction::query()
            ->selectRaw('budget_item_id, SUM(amount) as total_amount')
            ->where('user_id', $this->user->id)
            ->where('budget_id', $activeBudget->id)
            ->where('type', CategoryType::EXPENSE)
            ->whereYear('date', $now->year)
            ->whereMonth('date', $now->month)
            ->groupBy('budget_item_id')
            ->pluck('total_amount', 'budget_item_id');

        $totalExpense = $expenses->sum();

        if ($totalExpense <= 0) {
            return [];
        }

        return $budgetItems
            ->map(function ($budgetItem) use ($expenses, $totalExpense) {
                $amount = (int) ($expenses[$budgetItem->id] ?? 0);
                $percentage = round(($amount / $totalExpense) * 100, 2);

                return [
                    'category' => $budgetItem->category->name,
                    'amount' => $amount,
                    'percentage' => $percentage,
                ];
            })
            ->filter(fn ($item) => $item['amount'] > 0)
            ->values()
            ->toArray();
    }
}
