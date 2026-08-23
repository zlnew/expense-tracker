<?php

namespace App\Actions;

use App\Models\Balance;
use App\Models\SinkingFund;

class GetBalanceInsight extends Action
{
    /**
     * Derived insight for one balance.
     *
     * active  = final_amount (the authoritative ledger)
     * reserved = Σ accumulated across funds sourced from this balance
     * real     = active − reserved (what is spendable)
     *
     * Reserved reuses GetFundProgress::accumulated — single aggregate per
     * fund, no N+1. Derived, never stored, so it recomputes on every call.
     *
     * @return array{active: int, reserved: int, real: int}
     */
    public function handle(Balance|int $balance): array
    {
        $balance = $balance instanceof Balance
            ? $balance
            : Balance::query()->findOrFail($balance);

        $active = (int) $balance->final_amount;

        // Single query for the reserved total — sum progress per fund where
        // this balance is the source. Eager-load contributions to avoid N+1
        // when GetFundProgress needs them.
        $funds = SinkingFund::query()
            ->where('user_id', $balance->user_id)
            ->where('from_balance_id', $balance->id)
            ->with('contributions')
            ->get();

        $reserved = 0;
        foreach ($funds as $fund) {
            $progress = GetFundProgress::run($fund);
            $reserved += (int) ($progress['accumulated'] ?? 0);
        }

        return [
            'active' => $active,
            'reserved' => $reserved,
            'real' => $active - $reserved,
        ];
    }
}
