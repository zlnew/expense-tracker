<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * US-1 M1 backfill: anchor every legacy sinking fund to a source balance.
 *
 * One-time, idempotent — only rows where from_balance_id is still null are
 * touched. Resolution order per user:
 *   1. the flagged primary balance;
 *   2. fallback: the user's oldest balance (lowest id);
 *   3. no balances at all → left null (fund keeps working; validation will
 *      require an explicit source on its next edit).
 *
 * Lives outside the migration so the resolution rules are unit-testable.
 */
class BackfillFundSourceBalance
{
    public static function run(): int
    {
        $touched = 0;

        $funds = DB::table('sinking_funds')
            ->whereNull('from_balance_id')
            ->get(['id', 'user_id']);

        foreach ($funds as $fund) {
            $primaryId = DB::table('balances')
                ->where('user_id', $fund->user_id)
                ->where('is_primary', true)
                ->orderBy('id')
                ->value('id');

            if (! $primaryId) {
                $primaryId = DB::table('balances')
                    ->where('user_id', $fund->user_id)
                    ->orderBy('id')
                    ->value('id');
            }

            if ($primaryId) {
                DB::table('sinking_funds')
                    ->where('id', $fund->id)
                    ->update(['from_balance_id' => $primaryId]);
                $touched++;
            }
        }

        return $touched;
    }
}
