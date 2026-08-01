<?php

namespace App\Actions;

use App\DTO\TransactionsData;
use App\Models\Transaction;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class StoreTransactions extends Action
{
    public function __construct(
        private readonly TransactionsData $data,
    ) {}

    public function handle(): void
    {
        $user = Auth::user();

        $saved = DB::transaction(function () {
            $transactions = $this->data->items;

            return collect($transactions)->map(function ($t) {
                return SaveTransaction::run(new Transaction, $t);
            });
        });

        // Fire alerts after the DB transaction commits so webhook latency
        // never holds row locks.
        foreach ($saved as $transaction) {
            CheckBudgetAlerts::run($user, $transaction);
        }
    }
}
