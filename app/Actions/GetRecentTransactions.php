<?php

namespace App\Actions;

use App\DTO\TransactionData;
use App\Models\Budget;
use App\Models\Transaction;
use App\Models\User;
use Spatie\LaravelData\DataCollection;

class GetRecentTransactions extends Action
{
    public readonly User $user;

    public function __construct(User|int $user)
    {
        $this->user = $user instanceof User
            ? $user
            : User::query()->findOrFail($user);
    }

    public function handle(): DataCollection
    {
        $now = now();

        $activeBudget = Budget::query()
            ->where('user_id', $this->user->id)
            ->where('is_active', true)
            ->first();

        if (! $activeBudget) {
            return new DataCollection(TransactionData::class, []);
        }

        $transactions = Transaction::query()
            ->with(['category', 'balance'])
            ->where('user_id', $this->user->id)
            ->where('budget_id', $activeBudget->id)
            ->whereYear('date', $now->year)
            ->whereMonth('date', $now->month)
            ->orderBy('date', 'desc')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        return TransactionData::collect($transactions, DataCollection::class);
    }
}
