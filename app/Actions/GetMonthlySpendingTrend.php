<?php

namespace App\Actions;

use App\Enums\CategoryType;
use App\Models\Budget;
use App\Models\Transaction;
use App\Models\User;

class GetMonthlySpendingTrend extends Action
{
    public readonly User $user;

    private readonly Budget $activeBudget;

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

        $cutoffDay = $this->activeBudget->cutoff_day;

        $cycleMonthSql = "
            CASE
                WHEN EXTRACT(DAY FROM date) > {$cutoffDay}
                    THEN EXTRACT(MONTH FROM (date + INTERVAL '1 month'))
                ELSE EXTRACT(MONTH FROM date)
            END
        ";

        $cycleYearSql = "
            CASE
                WHEN EXTRACT(DAY FROM date) > {$cutoffDay}
                    THEN EXTRACT(YEAR FROM (date + INTERVAL '1 month'))
                ELSE EXTRACT(YEAR FROM date)
            END
        ";

        $now = now();

        $transactions = Transaction::query()
            ->selectRaw("
                CAST({$cycleMonthSql} AS INTEGER) as month,
                type,
                SUM(amount) as total_amount
            ")
            ->where('user_id', $this->user->id)
            ->where('budget_id', $this->activeBudget->id)
            ->whereRaw("CAST({$cycleYearSql} AS INTEGER) = ?", [$now->year])
            ->groupBy('month', 'type')
            ->get();

        $grouped = $transactions->groupBy('month');

        return collect(range(1, 12))
            ->map(function ($month) use ($grouped) {
                $items = $grouped->get($month, collect());

                $income = (int) (
                    $items->firstWhere('type', CategoryType::INCOME->value)?->total_amount
                    ?? 0
                );

                $expense = (int) (
                    $items->firstWhere('type', CategoryType::EXPENSE->value)?->total_amount
                    ?? 0
                );

                return [
                    'month' => $month,
                    'income' => $income,
                    'expense' => $expense,
                ];
            })
            ->toArray();
    }
}
