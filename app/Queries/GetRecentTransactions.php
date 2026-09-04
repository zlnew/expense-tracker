<?php

namespace App\Queries;

use App\DTO\TransactionData;
use App\Models\Budget;
use App\Models\Transaction;
use App\Models\User;
use App\Support\BudgetCycle;
use Spatie\LaravelData\DataCollection;

class GetRecentTransactions extends Query
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
        $activeBudget = Budget::query()
            ->where('user_id', $this->user->id)
            ->where('is_active', true)
            ->first();

        if (! $activeBudget) {
            return new DataCollection(TransactionData::class, []);
        }

        // Use the budget cutoff cycle (not the calendar month) so the "recent
        // transactions" widget agrees with the summary cards + budget progress.
        [$start, $end] = BudgetCycle::currentCycleRange($activeBudget);

        $transactions = Transaction::query()
            ->with(['category', 'balance'])
            ->where('user_id', $this->user->id)
            ->where('budget_id', $activeBudget->id)
            ->whereBetween('date', [$start, $end])
            ->orderBy('date', 'desc')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        return TransactionData::collect($transactions, DataCollection::class);
    }
}
