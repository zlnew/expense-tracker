<?php

namespace App\Actions;

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
