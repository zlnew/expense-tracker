<?php

namespace App\Actions;

use App\Models\FundContribution;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;

class DeleteFundContribution extends Action
{
    public function __construct(
        private readonly FundContribution $contribution,
    ) {}

    public function handle(): void
    {
        DB::transaction(function () {
            $groupId = $this->contribution->group_id;

            if ($groupId && $this->contribution->type === 'withdrawal') {
                // Cascading delete of the paired expense transaction, if any.
                $linkedTransaction = Transaction::query()
                    ->where('transfer_group_id', $groupId)
                    ->first();

                $balanceId = $linkedTransaction?->balance_id;

                $this->contribution->delete();

                if ($linkedTransaction) {
                    $linkedTransaction->delete();

                    if ($balanceId) {
                        SyncBalance::run((int) $balanceId);
                    }
                }
            } else {
                $this->contribution->delete();
            }
        });
    }
}
