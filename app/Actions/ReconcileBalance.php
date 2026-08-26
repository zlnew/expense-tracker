<?php

namespace App\Actions;

use App\Models\Balance;

class ReconcileBalance extends Action
{
    public function __construct(
        private readonly Balance $balance,
        private readonly int $reconciledAmount,
        private readonly string $reconciledAt,
    ) {}

    public function handle(): void
    {
        $this->balance->reconciled_amount = $this->reconciledAmount;
        $this->balance->reconciled_at = $this->reconciledAt;
        $this->balance->save();
    }
}
