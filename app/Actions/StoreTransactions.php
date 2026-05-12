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
            foreach ($this->data->items as $trans) {
                SaveTransaction::run(new Transaction, $trans);
            }
        });
    }
}
