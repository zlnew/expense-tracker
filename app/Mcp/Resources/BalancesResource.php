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

        $ids = $balances->pluck('id')->all();
        $reservedById = GetBalanceInsight::reservedByBalanceId($ids);

        $rows = [];
        $totalReal = 0;

        foreach ($balances as $b) {
            $active = (int) $b->final_amount;
            $reserved = (int) ($reservedById[$b->id] ?? 0);
            $real = $active - $reserved;

            $totalReal += $real;

            $rows[] = [
                'id' => $b->id,
                'name' => $b->name,
                'is_primary' => (bool) $b->is_primary,
                'active_amount' => $active,
                'reserved_amount' => $reserved,
                'real_amount' => $real,
                'real_balance' => $real,
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
