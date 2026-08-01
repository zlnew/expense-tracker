<?php

namespace App\Actions;

use App\Models\RecurringTransaction;

class DeleteRecurringTransaction extends Action
{
    public function __construct(
        private readonly RecurringTransaction $recurringTransaction,
    ) {}

    public function handle(): void
    {
        $this->recurringTransaction->delete();
    }
}
