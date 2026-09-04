<?php

namespace App\Console\Commands;

use App\Actions\SyncBalance;
use App\Enums\CategoryType;
use App\Models\Balance;
use App\Models\BudgetItem;
use App\Models\SinkingFund;
use App\Models\Transaction;
use App\Models\User;
use App\Queries\GetBalanceInsight;
use App\Queries\GetFundProgress;
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
            $this->auditBalances($user, $isDryRun);
            $this->auditBudgetItems($user, $isDryRun, $pruneZero);
            $this->auditSinkingFunds($user);
            $this->newLine();
        }

        $this->info('Integrity audit completed successfully.');

        return 0;
    }

    private function auditBalances(User $user, bool $isDryRun): void
    {
        $balances = Balance::query()
            ->where('user_id', $user->id)
            ->orderBy('is_primary', 'desc')
            ->orderBy('name')
            ->get();

        if ($balances->isEmpty()) {
            $this->line('  No balances found.');

            return;
        }

        $ids = $balances->pluck('id')->all();
        $reservedById = GetBalanceInsight::reservedByBalanceId($ids);

        $tableRows = [];
        $discrepanciesCount = 0;

        foreach ($balances as $b) {
            $totals = Transaction::query()
                ->where('balance_id', $b->id)
                ->selectRaw('
                    COALESCE(SUM(CASE WHEN type = ? THEN amount ELSE 0 END), 0) AS incomes,
                    COALESCE(SUM(CASE WHEN type = ? THEN amount ELSE 0 END), 0) AS expenses
                ', [CategoryType::INCOME->value, CategoryType::EXPENSE->value])
                ->first();

            $expectedFinal = ($b->initial_amount ?? 0) + (int) $totals->incomes - (int) $totals->expenses;
            $currentFinal = (int) $b->final_amount;
            $hasDiff = $expectedFinal !== $currentFinal;

            if ($hasDiff) {
                $discrepanciesCount++;
                if (! $isDryRun) {
                    SyncBalance::run($b);
                    $status = '<fg=yellow>FIXED (Resynced)</>';
                } else {
                    $status = '<fg=red>DISCREPANCY (Dry Run)</>';
                }
            } else {
                $status = '<fg=green>MATCH</>';
            }

            $reserved = (int) ($reservedById[$b->id] ?? 0);
            $real = $expectedFinal - $reserved;

            $tableRows[] = [
                $b->id,
                $b->name.($b->is_primary ? ' (Primary)' : ''),
                'Rp '.number_format($currentFinal, 0, ',', '.'),
                'Rp '.number_format($expectedFinal, 0, ',', '.'),
                'Rp '.number_format($reserved, 0, ',', '.'),
                'Rp '.number_format($real, 0, ',', '.'),
                $status,
            ];
        }

        $this->table(
            ['ID', 'Account', 'Stored Final', 'Computed Final', 'Reserved', 'Real Balance', 'Status'],
            $tableRows
        );

        if ($discrepanciesCount === 0) {
            $this->line('  <fg=green>✓ All balances perfectly match transaction ledgers.</>');
        }
    }

    private function auditBudgetItems(User $user, bool $isDryRun, bool $pruneZero): void
    {
        $zeroPlanned = BudgetItem::query()
            ->join('budgets', 'budgets.id', '=', 'budget_items.budget_id')
            ->where('budgets.user_id', $user->id)
            ->where('budget_items.planned_amount', 0)
            ->select('budget_items.*')
            ->with(['category', 'budget'])
            ->get();

        if ($zeroPlanned->isEmpty()) {
            $this->line('  <fg=green>✓ No zero-planned phantom budget items found.</>');

            return;
        }

        $prunedCount = 0;
        $activeUnplannedCount = 0;

        foreach ($zeroPlanned as $item) {
            $linkedTxnCount = Transaction::query()->where('budget_item_id', $item->id)->count();

            if ($linkedTxnCount === 0) {
                if ($pruneZero && ! $isDryRun) {
                    $item->delete();
                    $prunedCount++;
                }
            } else {
                $activeUnplannedCount++;
            }
        }

        $this->line("  <fg=yellow>Found {$zeroPlanned->count()} budget item(s) with 0 planned envelope:</>");
        $this->line("    - {$activeUnplannedCount} item(s) linked to actual transactions (retained as unbudgeted spend).");
        if ($pruneZero && ! $isDryRun) {
            $this->line("    - <fg=green>{$prunedCount} orphaned item(s) successfully pruned.</>");
        } else {
            $orphaned = $zeroPlanned->count() - $activeUnplannedCount;
            if ($orphaned > 0) {
                $this->line("    - {$orphaned} orphaned item(s) with 0 transactions. Pass --prune-zero-budget-items to clean them.");
            }
        }
    }

    private function auditSinkingFunds(User $user): void
    {
        $funds = SinkingFund::query()
            ->where('user_id', $user->id)
            ->with(['sourceBalance', 'category'])
            ->get();

        if ($funds->isEmpty()) {
            return;
        }

        $fundRows = [];
        foreach ($funds as $f) {
            $progress = GetFundProgress::run($f);
            $accumulated = (int) $progress['accumulated'];
            $target = (int) $f->target_amount;
            $percent = $progress['percent'].'%';

            $fundRows[] = [
                $f->id,
                $f->name,
                $f->sourceBalance?->name ?? 'None',
                'Rp '.number_format($accumulated, 0, ',', '.'),
                'Rp '.number_format($target, 0, ',', '.'),
                $percent,
                $f->next_due_date ? $f->next_due_date->toDateString() : 'None',
            ];
        }

        $this->table(
            ['Fund ID', 'Fund Name', 'Source Account', 'Accumulated', 'Target', 'Progress', 'Next Due'],
            $fundRows
        );
    }
}
