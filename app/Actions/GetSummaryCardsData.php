<?php

namespace App\Actions;

use App\Enums\CategoryType;
use App\Models\Balance;
use App\Models\Budget;
use App\Models\BudgetItem;
use App\Models\Transaction;
use App\Models\User;

class GetSummaryCardsData extends Action
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

    public function handle()
    {
        $totalBalance = $this->getTotalBalance();
        $currentMonthExpenses = $this->getCurrentMonthExpenses();
        $currentMonthIncomes = $this->getCurrentMonthIncomes();
        $budgetRemaining = $this->getPlannedBudget() - $currentMonthExpenses;

        return [
            'total_balance' => $totalBalance,
            'current_month_expenses' => $currentMonthExpenses,
            'current_month_incomes' => $currentMonthIncomes,
            'budget_remaining' => $budgetRemaining,
        ];
    }

    private function getTotalBalance(): int
    {
        return (int) Balance::query()
            ->where('user_id', $this->user->id)
            ->sum('final_amount');
    }

    private function getCurrentMonthExpenses(): int
    {
        if (! $this->activeBudget) {
            return 0;
        }

        $now = now();

        return (int) Transaction::query()
            ->where('user_id', $this->user->id)
            ->where('budget_id', $this->activeBudget->id)
            ->where('type', CategoryType::EXPENSE)
            ->whereYear('date', $now->year)
            ->whereMonth('date', $now->month)
            ->sum('amount');
    }

    private function getCurrentMonthIncomes(): int
    {
        if (! $this->activeBudget) {
            return 0;
        }

        $now = now();

        return (int) Transaction::query()
            ->where('user_id', $this->user->id)
            ->where('budget_id', $this->activeBudget->id)
            ->where('type', CategoryType::INCOME)
            ->whereYear('date', $now->year)
            ->whereMonth('date', $now->month)
            ->sum('amount');
    }

    private function getPlannedBudget(): int
    {
        if (! $this->activeBudget) {
            return 0;
        }

        return (int) BudgetItem::query()
            ->where('budget_id', $this->activeBudget->id)
            ->where('type', CategoryType::EXPENSE)
            ->sum('planned_amount');
    }
}
