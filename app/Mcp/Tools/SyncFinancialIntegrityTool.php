<?php

namespace App\Mcp\Tools;

use App\Actions\SyncFinancialIntegrity;
use App\Models\User;

class SyncFinancialIntegrityTool implements ToolInterface
{
    public function name(): string
    {
        return 'sync_financial_integrity';
    }

    public function description(): string
    {
        return 'Audit financial ledger integrity, verify account final_amounts against transactions, audit sinking funds reserves, and optionally resync balances and prune orphaned zero-planned envelopes.';
    }

    public function schema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'dry_run' => [
                    'type' => 'boolean',
                    'description' => 'If true (default), only audit and report discrepancies without modifying records. If false, synchronizes drifting balances.',
                ],
                'prune_zero_budget_items' => [
                    'type' => 'boolean',
                    'description' => 'If true, prunes 0-planned budget items that have no linked transactions. Defaults to false.',
                ],
            ],
        ];
    }

    public function execute(User $user, array $arguments): array
    {
        $dryRun = ! isset($arguments['dry_run']) || (bool) $arguments['dry_run'];
        $pruneZero = ! empty($arguments['prune_zero_budget_items']);

        $res = SyncFinancialIntegrity::run($user, isDryRun: $dryRun, pruneZeroBudgetItems: $pruneZero);

        $modeText = $dryRun ? '🔍 DRY-RUN (Audit only, no changes applied)' : '⚡ ACTIVE (Discrepancies synchronized)';
        $output = "### Financial Integrity Audit ({$modeText})\n\n";

        // Balances summary
        $output .= "#### Accounts & Balances:\n";
        if (empty($res['balances'])) {
            $output .= "- No accounts found.\n";
        } else {
            foreach ($res['balances'] as $b) {
                $statusIcon = match ($b['status']) {
                    'FIXED' => '🔄 FIXED (Resynced)',
                    'DISCREPANCY' => '⚠️ DISCREPANCY',
                    default => '✅ MATCH',
                };
                $storedFmt = 'Rp '.number_format($b['stored_final'], 0, ',', '.');
                $computedFmt = 'Rp '.number_format($b['computed_final'], 0, ',', '.');
                $realFmt = 'Rp '.number_format($b['real_balance'], 0, ',', '.');
                $reservedFmt = 'Rp '.number_format($b['reserved'], 0, ',', '.');

                $output .= "- **{$b['name']}** (ID: {$b['id']}): {$statusIcon}\n"
                    ."  - Recorded: {$storedFmt} | Computed from ledger: {$computedFmt}\n"
                    ."  - Sinking Fund Reserved: {$reservedFmt} | Real Available: {$realFmt}\n";
            }
        }

        // Budget Envelopes
        $bi = $res['budget_items'];
        $output .= "\n#### Budget Envelopes Audit:\n";
        if ($bi['total_zero_planned'] === 0) {
            $output .= "- ✅ No zero-planned phantom budget envelopes found.\n";
        } else {
            $output .= "- Found {$bi['total_zero_planned']} budget envelope(s) with 0 planned amount:\n";
            $output .= "  - {$bi['active_unplanned']} active envelope(s) linked to actual transactions (retained as unbudgeted spend).\n";
            if ($pruneZero && ! $dryRun) {
                $output .= "  - ✅ {$bi['pruned']} orphaned envelope(s) successfully pruned.\n";
            } else {
                $output .= "  - {$bi['orphaned']} orphaned envelope(s) with 0 transactions (set `prune_zero_budget_items: true` to prune).\n";
            }
        }

        // Sinking Funds
        if (! empty($res['sinking_funds'])) {
            $output .= "\n#### Sinking Funds Progress:\n";
            foreach ($res['sinking_funds'] as $f) {
                $accFmt = 'Rp '.number_format($f['accumulated'], 0, ',', '.');
                $targetFmt = 'Rp '.number_format($f['target'], 0, ',', '.');
                $output .= "- **{$f['name']}** (Source: {$f['source_account']}): {$accFmt} / {$targetFmt} ({$f['percent']}%) - Next Due: {$f['next_due']}\n";
            }
        }

        return [
            'content' => [
                [
                    'type' => 'text',
                    'text' => trim($output),
                ],
            ],
        ];
    }
}
