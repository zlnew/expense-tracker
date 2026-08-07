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
        // Lock the balance row so two concurrent writes can't both read the
        // pre-write state and clobber each other's final_amount.
        $locked = Balance::query()
            ->whereKey($this->balance->id)
            ->lockForUpdate()
            ->first();

        if (! $locked) {
            return;
        }

        $totals = Transaction::query()
            ->where('balance_id', $locked->id)
            ->selectRaw('
                COALESCE(SUM(CASE WHEN type = ? THEN amount ELSE 0 END), 0) AS incomes,
                COALESCE(SUM(CASE WHEN type = ? THEN amount ELSE 0 END), 0) AS expenses
            ', [CategoryType::INCOME->value, CategoryType::EXPENSE->value])
            ->first();

        $locked->final_amount = ($locked->initial_amount ?? 0)
            + (int) $totals->incomes
            - (int) $totals->expenses;

        $locked->save();

        // Keep the caller's in-memory instance consistent with what was written.
        $this->balance->final_amount = $locked->final_amount;
    }
}
