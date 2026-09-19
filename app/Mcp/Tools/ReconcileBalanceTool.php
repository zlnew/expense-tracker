<?php

namespace App\Mcp\Tools;

use App\Actions\ReconcileBalance;
use App\Models\Balance;
use App\Models\User;

class ReconcileBalanceTool implements ToolInterface
{
    public function name(): string
    {
        return 'reconcile_balance';
    }

    public function description(): string
    {
        return 'Reconcile an account balance against real-world statement. Updates reconciled amount, detects any discrepancy (drift), and optionally auto-adjusts ledger to match.';
    }

    public function schema(): array
    {
        return [
            'type' => 'object',
            'required' => ['balance_id', 'actual_amount'],
            'properties' => [
                'balance_id' => [
                    'type' => 'integer',
                    'description' => 'The ID of the account / balance',
                ],
                'actual_amount' => [
                    'type' => 'integer',
                    'description' => 'The real-world statement amount in IDR',
                ],
                'reconciled_at' => [
                    'type' => 'string',
                    'description' => 'Reconciliation date (YYYY-MM-DD, defaults to today)',
                ],
                'auto_adjust' => [
                    'type' => 'boolean',
                    'description' => 'If true and a discrepancy (drift) is detected, automatically creates an adjustment transaction (expense or income) to immediately align the ledger balance with the real-world statement.',
                ],
            ],
        ];
    }

    public function execute(User $user, array $arguments): array
    {
        $balanceId = (int) ($arguments['balance_id'] ?? 0);
        $actualAmount = (int) ($arguments['actual_amount'] ?? 0);
        $reconciledAt = ! empty($arguments['reconciled_at']) ? $arguments['reconciled_at'] : now()->toDateString();
        $autoAdjust = (bool) ($arguments['auto_adjust'] ?? false);

        $balance = Balance::query()->where('user_id', $user->id)->find($balanceId);
        if (! $balance) {
            return [
                'content' => [['type' => 'text', 'text' => "Error: Balance #{$balanceId} not found or does not belong to user."]],
                'isError' => true,
            ];
        }

        $adjustment = ReconcileBalance::run($balance, $actualAmount, $reconciledAt, $autoAdjust);

        $fresh = $balance->fresh();
        $drift = $fresh->drift ?? 0;
        $driftFmt = 'Rp '.number_format(abs($drift), 0, ',', '.');
        $actualFmt = 'Rp '.number_format($actualAmount, 0, ',', '.');
        $computedFmt = 'Rp '.number_format($fresh->final_amount, 0, ',', '.');

        $status = $drift === 0
            ? '✅ Perfectly reconciled! Zero drift.'
            : ($drift > 0
                ? "⚠️ Discrepancy: Real balance is {$driftFmt} LOWER than recorded ledger ({$computedFmt})."
                : "⚠️ Discrepancy: Real balance is {$driftFmt} HIGHER than recorded ledger ({$computedFmt}).");

        $msg = "Account '{$fresh->name}' reconciled as of {$reconciledAt}:\n"
            ."- Actual Statement Amount: {$actualFmt}\n"
            ."- Recorded Computed Amount: {$computedFmt}\n"
            ."- Drift: {$status}";

        if ($adjustment) {
            $adjAmountFmt = 'Rp '.number_format($adjustment->amount, 0, ',', '.');
            $msg .= "\n- Auto-adjustment: Created transaction #{$adjustment->id} ({$adjustment->type->value}, {$adjAmountFmt}) to align ledger balance.";
        }

        return [
            'content' => [
                [
                    'type' => 'text',
                    'text' => $msg,
                ],
            ],
        ];
    }
}
