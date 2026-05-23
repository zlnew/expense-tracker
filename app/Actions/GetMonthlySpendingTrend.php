<?php

namespace App\Actions;

use App\Enums\CategoryType;
use App\Models\Budget;
use App\Models\Transaction;
use App\Models\User;

class GetMonthlySpendingTrend extends Action
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
        $now = now();

        $activeBudget = Budget::query()
            ->where('user_id', $this->user->id)
            ->where('is_active', true)
            ->first();

        if (! $activeBudget) {
            return [];
        }

        $driver = Transaction::query()->getConnection()->getDriverName();
        $monthSql = match ($driver) {
            'sqlite' => 'cast(strftime("%m", date) as integer)',
            'pgsql' => 'EXTRACT(MONTH FROM date)',
            default => 'MONTH(date)',
        };

        $transactions = Transaction::query()
            ->selectRaw("
                {$monthSql} as month,
                type,
                SUM(amount) as total_amount
            ")
            ->where('user_id', $this->user->id)
            ->where('budget_id', $activeBudget->id)
            ->whereYear('date', $now->year)
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
