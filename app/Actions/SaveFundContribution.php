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
 */
class SaveFundContribution extends Action
{
    public function __construct(
        private readonly SinkingFund $fund,
        private readonly FundContributionData $data,
    ) {}

    public function handle(): FundContribution
    {
        return DB::transaction(function () {
            // Serialization point with PayFromFund's lockForUpdate (D2).
            $locked = SinkingFund::query()
                ->whereKey($this->fund->id)
                ->lockForUpdate()
                ->firstOrFail();

            return $locked->contributions()->create([
                'user_id' => $locked->user_id,
                'type' => 'contribution',
                'amount' => $this->data->amount,
                'date' => $this->data->date,
                'transaction_id' => null,
                'description' => $this->data->description,
            ]);
        });
    }
}
