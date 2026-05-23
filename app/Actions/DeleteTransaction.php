<?php

namespace App\Actions;

use App\Models\Transaction;
use Illuminate\Support\Facades\DB;

class DeleteTransaction extends Action
{
    public function __construct(
        private readonly Transaction $transaction,
    ) {}

    public function handle(): void
    {
        DB::transaction(function () {
            $balanceId = $this->transaction->balance_id;

            $this->transaction->delete();

            SyncBalance::run($balanceId);
        });
    }
}
