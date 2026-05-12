<?php

namespace App\Actions;

use App\Models\Transaction;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DeleteTransaction extends Action
{
    public function __construct(
        private readonly Transaction $transaction,
    ) {}

    public function handle(): void
    {
        DB::transaction(function () {
            $userId = Auth::id();
            $budgetItemId = $this->transaction->budget_item_id;

            $this->transaction->delete();

            SyncBudgetItemAmounts::run($budgetItemId);
            SyncUserBalance::run($userId);
        });
    }
}
