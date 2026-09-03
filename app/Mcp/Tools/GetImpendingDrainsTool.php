<?php

namespace App\Mcp\Tools;

use App\Actions\GetImpendingDrains;
use App\Models\User;

class GetImpendingDrainsTool implements ToolInterface
{
    public function name(): string
    {
        return 'get_impending_drains';
    }

    public function description(): string
    {
        return 'Get upcoming cash outflows (sinking fund contribution dues and recurring bills) over the next N days (default: 60) with projected post-drain balances and negative balance alerts.';
    }

    public function schema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'horizon_days' => [
                    'type' => 'integer',
                    'description' => 'Forecasting window in days (default: 60, min: 1, max: 365)',
                ],
            ],
        ];
    }

    public function execute(User $user, array $arguments): array
    {
        $window = (int) ($arguments['horizon_days'] ?? 60);
        $window = max(1, min(365, $window));

        $drains = GetImpendingDrains::run($user->id, $window);

        $totalOutflow = $drains['total_impending_outflow'] ?? 0;
        $totalFmt = 'Rp '.number_format($totalOutflow, 0, ',', '.');
        $hasNegativeWarning = ! empty($drains['has_negative_warning']);

        $summaryText = "Impending Cash Outflows ({$drains['from']} to {$drains['until']}, {$window} days):\n"
            ."Total Outflow: {$totalFmt}\n"
            .($hasNegativeWarning ? "⚠️ WARNING: One or more accounts are projected to go negative!\n" : '')
            ."\nUpcoming Dues:\n".json_encode($drains['items'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            ."\n\nPer-Account Projections:\n".json_encode($drains['per_balance'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return [
            'content' => [
                [
                    'type' => 'text',
                    'text' => $summaryText,
                ],
            ],
        ];
    }
}
