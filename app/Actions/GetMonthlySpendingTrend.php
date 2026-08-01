<?php

namespace App\Actions;

use App\Enums\CategoryType;
use App\Models\Budget;
use App\Models\Transaction;
use App\Models\User;

class GetMonthlySpendingTrend extends Action
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
        if ($this->activeBudget === null) {
            return [];
        }

        $cutoffDay = $this->activeBudget->cutoff_day;
        $currentYear = now()->year;

        $subquery = Transaction::query()
            ->selectRaw("
                *,
                CASE
                    WHEN EXTRACT(DAY FROM date) > LEAST(
                        ?,
                        EXTRACT(DAY FROM (DATE_TRUNC('month', date) + INTERVAL '1 month' - INTERVAL '1 day'))
                    )
                        THEN date + INTERVAL '1 month'
                    ELSE date
                END AS cycle_date
            ", [$cutoffDay])
            ->where('user_id', $this->user->id)
            ->where('budget_id', $this->activeBudget->id);

        $transactions = Transaction::query()
            ->fromSub($subquery, 'txn')
            ->selectRaw('
                CAST(EXTRACT(MONTH FROM cycle_date) AS INTEGER) AS month,
                type,
                SUM(amount) AS total_amount
            ')
            ->whereRaw('CAST(EXTRACT(YEAR FROM cycle_date) AS INTEGER) = ?', [$currentYear])
            ->groupBy('month', 'type')
            ->get();

        $grouped = $transactions->groupBy('month');

        return collect(range(1, 12))
            ->map(function (int $month) use ($grouped): array {
                $items = $grouped->get($month, collect());

                $income = (int) (
                    $items->firstWhere('type', CategoryType::INCOME->value)?->total_amount ?? 0
                );

                $expense = (int) (
                    $items->firstWhere('type', CategoryType::EXPENSE->value)?->total_amount ?? 0
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
