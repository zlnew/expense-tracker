<?php

namespace App\Console\Commands;

use App\Actions\SyncFinancialIntegrity;
use App\Models\User;
use Illuminate\Console\Command;

class SyncFinancialIntegrityCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:sync-financial-integrity
                            {--dry-run : Only report discrepancies without modifying records}
                            {--prune-zero-budget-items : Prune budget items with 0 planned amount and 0 linked transactions}
                            {--user= : Specific user ID to process (defaults to all users)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Verify ledger integrity, resync balance final_amounts from atomic transactions, audit sinking funds, and prune orphaned envelopes';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $isDryRun = (bool) $this->option('dry-run');
        $pruneZero = (bool) $this->option('prune-zero-budget-items');
        $userId = $this->option('user');

        $users = $userId
            ? User::query()->where('id', $userId)->get()
            : User::query()->orderBy('id')->get();

        if ($users->isEmpty()) {
            $this->error('No users found.');

            return 1;
        }

        $this->info('=== Expense Tracker Financial Integrity Audit ===');
        if ($isDryRun) {
            $this->warn('Mode: DRY-RUN (No records will be modified)');
        } else {
            $this->comment('Mode: ACTIVE (Discrepancies will be synchronized)');
        }
        $this->newLine();

        foreach ($users as $user) {
            $this->line("<fg=cyan>User #{$user->id}: {$user->name} ({$user->email})</>");
            $res = SyncFinancialIntegrity::run($user, $isDryRun, $pruneZero);

            // Balances
            if (empty($res['balances'])) {
                $this->line('  No balances found.');
            } else {
                $tableRows = [];
                foreach ($res['balances'] as $b) {
                    $status = match ($b['status']) {
                        'FIXED' => '<fg=yellow>FIXED (Resynced)</>',
                        'DISCREPANCY' => '<fg=red>DISCREPANCY (Dry Run)</>',
                        default => '<fg=green>MATCH</>',
                    };

                    $tableRows[] = [
                        $b['id'],
                        $b['name'].($b['is_primary'] ? ' (Primary)' : ''),
                        'Rp '.number_format($b['stored_final'], 0, ',', '.'),
                        'Rp '.number_format($b['computed_final'], 0, ',', '.'),
                        'Rp '.number_format($b['reserved'], 0, ',', '.'),
                        'Rp '.number_format($b['real_balance'], 0, ',', '.'),
                        $status,
                    ];
                }

                $this->table(
                    ['ID', 'Account', 'Stored Final', 'Computed Final', 'Reserved', 'Real Balance', 'Status'],
                    $tableRows
                );

                if ($res['discrepancies_count'] === 0) {
                    $this->line('  <fg=green>✓ All balances perfectly match transaction ledgers.</>');
                }
            }

            // Budget Envelopes
            $bi = $res['budget_items'];
            if ($bi['total_zero_planned'] === 0) {
                $this->line('  <fg=green>✓ No zero-planned phantom budget items found.</>');
            } else {
                $this->line("  <fg=yellow>Found {$bi['total_zero_planned']} budget item(s) with 0 planned envelope:</>");
                $this->line("    - {$bi['active_unplanned']} item(s) linked to actual transactions (retained as unbudgeted spend).");
                if ($pruneZero && ! $isDryRun) {
                    $this->line("    - <fg=green>{$bi['pruned']} orphaned item(s) successfully pruned.</>");
                } else {
                    if ($bi['orphaned'] > 0) {
                        $this->line("    - {$bi['orphaned']} orphaned item(s) with 0 transactions. Pass --prune-zero-budget-items to clean them.");
                    }
                }
            }

            // Sinking Funds
            if (! empty($res['sinking_funds'])) {
                $fundRows = [];
                foreach ($res['sinking_funds'] as $f) {
                    $fundRows[] = [
                        $f['id'],
                        $f['name'],
                        $f['source_account'],
                        'Rp '.number_format($f['accumulated'], 0, ',', '.'),
                        'Rp '.number_format($f['target'], 0, ',', '.'),
                        $f['percent'].'%',
                        $f['next_due'],
                    ];
                }

                $this->table(
                    ['Fund ID', 'Fund Name', 'Source Account', 'Accumulated', 'Target', 'Progress', 'Next Due'],
                    $fundRows
                );
            }

            $this->newLine();
        }

        $this->info('Integrity audit completed successfully.');

        return 0;
    }
}
