<?php

namespace App\Actions;

use App\DTO\FundContributionData;
use App\Models\FundContribution;
use App\Models\SinkingFund;
use Illuminate\Support\Facades\DB;

/**
 * Set-aside: write a contribution ledger row.
 *
 * Pure per spec D1 — creates NO transaction, touches NO balance, never calls
 * SyncBalance. The fund row is locked while writing so a concurrent
 * withdrawal (PayFromFund, which re-checks the reserve under the same lock)
 * can't read a stale accumulated value.
 *
 * Envelope-basis (2026-08-16 spec): after the ledger row commits, run
 * CheckFundBudgetAlerts — the reservation is the budget movement, so a
 * set-aside can cross an 80/100% budget threshold. Alerts fire AFTER the
 * DB transaction commits (webhook latency never holds row locks) and only
 * then is the new set-aside visible to the actuals query.
 */
class SaveFundContribution extends Action
{
    public function __construct(
        private readonly SinkingFund $fund,
        private readonly FundContributionData $data,
    ) {}

    public function handle(): FundContribution
    {
        $contribution = DB::transaction(function () {
            // Serialization point with PayFromFund's lockForUpdate (D2).
            $locked = SinkingFund::query()
                ->whereKey($this->fund->id)
                ->lockForUpdate()
                ->firstOrFail();

            return $locked->contributions()->create([
                'user_id' => $locked->user_id,
                'type' => 'contribution',
                'amount' => $this->data->amount,
                // Normalize to start-of-day: the DTO cast preserves the current
                // time-of-day for date-only strings, and budget windows
                // (period_end reads as startOfDay via the date cast) would
                // otherwise exclude a set-aside made on the last day of a
                // period — the silent rollover double-count returns.
                'date' => $this->data->date->startOfDay(),
                'transaction_id' => null,
                'description' => $this->data->description,
            ]);
        });

        // After commit (StoreTransactions pattern) so webhook latency never
        // holds row locks and the new set-aside is already in the actuals.
        CheckFundBudgetAlerts::run($this->fund, $contribution);

        return $contribution;
    }
}
