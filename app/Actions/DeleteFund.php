<?php

namespace App\Actions;

use App\Models\SinkingFund;

class DeleteFund extends Action
{
    public function __construct(
        private readonly SinkingFund $fund,
    ) {}

    public function handle(): void
    {
        // Soft delete only — the ledger stays intact (D7, domain rule 5).
        $this->fund->delete();
    }
}
