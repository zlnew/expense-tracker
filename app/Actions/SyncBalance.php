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
        $totals = Transaction::query()
            ->where('balance_id', $this->balance->id)
            ->selectRaw("
                COALESCE(SUM(CASE WHEN type = ? THEN amount ELSE 0 END), 0) AS incomes,
                COALESCE(SUM(CASE WHEN type = ? THEN amount ELSE 0 END), 0) AS expenses
            ", [CategoryType::INCOME->value, CategoryType::EXPENSE->value])
            ->first();

        $this->balance->final_amount = ($this->balance->initial_amount ?? 0)
            + (int) $totals->incomes
            - (int) $totals->expenses;

        $this->balance->save();
    }
}
