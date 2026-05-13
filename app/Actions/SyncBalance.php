<?php

namespace App\Actions;

use App\Enums\CategoryType;
use App\Models\Balance;
use App\Models\Transaction;

class SyncBalance extends Action
{
    public readonly Balance $balance;

    public function __construct(Balance|int $balance)
    {
        $this->balance = $balance instanceof Balance
            ? $balance
            : Balance::query()->findOrFail($balance);
    }

    public function handle(): void
    {
        $incomes = Transaction::query()
            ->where('balance_id', $this->balance->id)
            ->where('type', CategoryType::INCOME)
            ->get();

        $expenses = Transaction::query()
            ->where('balance_id', $this->balance->id)
            ->where('type', CategoryType::EXPENSE)
            ->get();

        $initialAmount = $this->balance->initial_amount ?? 0;
        $incomesAmount = (int) $incomes->sum('amount');
        $expensesAmount = (int) $expenses->sum('amount');

        $this->balance->final_amount = $initialAmount + $incomesAmount - $expensesAmount;
        $this->balance->save();
    }
}
