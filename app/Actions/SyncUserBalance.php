<?php

namespace App\Actions;

use App\Enums\CategoryType;
use App\Models\Balance;
use App\Models\Transaction;
use App\Models\User;

class SyncUserBalance extends Action
{
    public readonly User $user;

    public function __construct(User|int $user)
    {
        $this->user = $user instanceof User
            ? $user
            : User::query()->findOrFail($user);
    }

    public function handle(): void
    {
        $incomes = Transaction::query()
            ->where('user_id', $this->user->id)
            ->where('type', CategoryType::INCOME)
            ->get();

        $expenses = Transaction::query()
            ->where('user_id', $this->user->id)
            ->where('type', CategoryType::EXPENSE)
            ->get();

        $initialAmount = $this->balance->initial_amount ?? 0;
        $incomesAmount = (int) $incomes->sum('amount');
        $expensesAmount = (int) $expenses->sum('amount');

        $balance = Balance::query()->where('user_id', $this->user->id)->firstOrFail();
        $balance->final_amount = $initialAmount + $incomesAmount - $expensesAmount;
        $balance->save();
    }
}
