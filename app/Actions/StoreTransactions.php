<?php

namespace App\Actions;

use App\DTO\TransactionsData;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;

class StoreTransactions extends Action
{
    public function __construct(
        private readonly TransactionsData $data,
    ) {}

    public function handle(): void
    {
        DB::transaction(function () {
            $transactions = $this->data->items;

            foreach ($transactions as $t) {
                SaveTransaction::run(new Transaction, $t);
            }
        });
    }
}
