<?php

namespace App\Mcp\Tools;

use App\Actions\GetBalanceInsight;
use App\Models\Balance;
use App\Models\User;

class GetBalanceSummaryTool implements ToolInterface
{
    public function name(): string
    {
        return 'get_balance_summary';
    }

    public function description(): string
    {
        return 'Get a breakdown of all financial accounts/balances with Active (stored), Reserved (for sinking funds), and Real (free-to-spend) amounts, along with total Net Worth.';
    }

    public function schema(): array
    {
        return [
            'type' => 'object',
            'properties' => [],
        ];
    }

    public function execute(User $user, array $arguments): array
    {
        $balances = Balance::query()
            ->where('user_id', $user->id)
            ->orderBy('is_primary', 'desc')
            ->orderBy('name')
            ->get();

        $rows = [];
        $totalActive = 0;
        $totalReserved = 0;
        $totalReal = 0;

        foreach ($balances as $b) {
            $insight = GetBalanceInsight::run($b);
            $active = $insight['active'];
            $reserved = $insight['reserved'];
            $real = $insight['real'];

            $totalActive += $active;
            $totalReserved += $reserved;
            $totalReal += $real;

            $rows[] = [
                'id' => $b->id,
                'name' => $b->name,
                'is_primary' => (bool) $b->is_primary,
                'active' => $active,
                'active_formatted' => 'Rp '.number_format($active, 0, ',', '.'),
                'reserved' => $reserved,
                'reserved_formatted' => 'Rp '.number_format($reserved, 0, ',', '.'),
                'real' => $real,
                'real_formatted' => 'Rp '.number_format($real, 0, ',', '.'),
                'reconciled_amount' => $b->reconciled_amount,
                'drift' => $b->drift,
            ];
        }

        $summary = [
            'total_net_worth' => $totalReal,
            'total_net_worth_formatted' => 'Rp '.number_format($totalReal, 0, ',', '.'),
            'total_active' => $totalActive,
            'total_active_formatted' => 'Rp '.number_format($totalActive, 0, ',', '.'),
            'total_reserved' => $totalReserved,
            'total_reserved_formatted' => 'Rp '.number_format($totalReserved, 0, ',', '.'),
            'accounts' => $rows,
        ];

        $text = 'Financial Balances Summary (Net Worth / Total Real: Rp '.number_format($totalReal, 0, ',', '.')."):\n".json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return [
            'content' => [
                [
                    'type' => 'text',
                    'text' => $text,
                ],
            ],
        ];
    }
}
