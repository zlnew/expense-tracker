<?php

namespace App\Mcp\Resources;

use App\Actions\GetBalanceInsight;
use App\Models\Balance;
use App\Models\User;

class BalancesResource implements ResourceInterface
{
    public function uri(): string
    {
        return 'expense-tracker://balances';
    }

    public function name(): string
    {
        return 'Account Balances & Real Liquidity';
    }

    public function description(): string
    {
        return 'Live financial accounts with Active, Reserved (sinking funds), and Real spendable balance breakdown.';
    }

    public function mimeType(): string
    {
        return 'application/json';
    }

    public function read(User $user): string
    {
        $balances = Balance::query()
            ->where('user_id', $user->id)
            ->orderBy('is_primary', 'desc')
            ->orderBy('name')
            ->get();

        $rows = [];
        $totalReal = 0;

        foreach ($balances as $b) {
            $insight = GetBalanceInsight::run($b);
            $totalReal += $insight['real'];

            $rows[] = [
                'id' => $b->id,
                'name' => $b->name,
                'is_primary' => (bool) $b->is_primary,
                'active_amount' => $insight['active'],
                'reserved_amount' => $insight['reserved'],
                'real_amount' => $insight['real'],
                'reconciled_amount' => $b->reconciled_amount,
                'drift' => $b->drift,
            ];
        }

        return json_encode([
            'net_worth' => $totalReal,
            'balances' => $rows,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }
}
