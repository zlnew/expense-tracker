<?php

namespace App\Actions;

use App\Models\Transaction;

class DeleteTransaction extends Action
{
    public function __construct(
        private readonly Transaction $transaction,
    ) {}

    public function handle(): void
    {
        $this->transaction->delete();
    }
}
