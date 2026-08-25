<?php

namespace App\Actions;

use App\Models\FundContribution;
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
            // A transfer is recorded as a linked pair (source expense +
            // destination income sharing one transfer_group_id). Deleting one
            // leg deletes the pair so balances can never drift apart.
            // Likewise, a fund withdrawal's expense shares its group_id with
            // the paired FundContribution (withdrawal) row; deleting the
            // expense also soft-deletes that withdrawal ledger row.
            $groupId = $this->transaction->transfer_group_id;
            $balanceIds = collect([$this->transaction->balance_id]);

            if ($groupId) {
                $pair = Transaction::query()
                    ->where('transfer_group_id', $groupId)
                    ->where('id', '!=', $this->transaction->id)
                    ->get();

                $balanceIds->push($pair->pluck('balance_id')->all());

                $this->transaction->delete();

                foreach ($pair as $leg) {
                    $leg->delete();
                }

                // Paired fund withdrawal ledger row, if any, shares the same
                // group_id. Hard-delete it (no SoftDeletes on fund_contributions):
                // the balance already resyncs from transactions, and the reserve
                // derives from the ledger — removing the leg pair + the ledger
                // row restores both planes atomically.
                $linkedWithdrawal = FundContribution::query()
                    ->where('group_id', $groupId)
                    ->where('type', 'withdrawal')
                    ->first();

                if ($linkedWithdrawal) {
                    // The linked withdrawal's transaction_id is the very row we
                    // just deleted (or one of the legs); avoid double-delete.
                    $linkedWithdrawal->delete();
                }
            } else {
                $this->transaction->delete();
            }

            // Resync every affected balance after the deletions.
            foreach ($balanceIds->unique()->flatten() as $balanceId) {
                SyncBalance::run((int) $balanceId);
            }
        });
    }
}
