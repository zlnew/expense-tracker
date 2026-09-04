<?php

namespace App\Actions;

use App\Enums\CategoryType;
use App\Models\Balance;
use App\Models\BudgetItem;
use App\Models\SinkingFund;
use App\Models\Transaction;
use App\Models\User;
use App\Queries\GetBalanceInsight;
use App\Queries\GetFundProgress;

class SyncFinancialIntegrity extends Action
{
    public function __construct(
        public readonly User $user,
        public readonly bool $isDryRun = true,
        public readonly bool $pruneZeroBudgetItems = false,
    ) {}

    /**
     * @return array{
     *     user: array{id: int, name: string, email: string},
     *     balances: array<int, array{
     *         id: int,
     *         name: string,
     *         is_primary: bool,
     *         stored_final: int,
     *         computed_final: int,
     *         reserved: int,
     *         real_balance: int,
     *         status: string,
     *     }>,
     *     discrepancies_count: int,
     *     fixed_count: int,
     *     budget_items: array{
     *         total_zero_planned: int,
     *         active_unplanned: int,
     *         orphaned: int,
     *         pruned: int,
     *     },
     *     sinking_funds: array<int, array{
     *         id: int,
     *         name: string,
     *         source_account: string,
     *         accumulated: int,
     *         target: int,
     *         percent: float|int,
     *         next_due: string,
     *     }>,
     * }
     */
    public function handle(): array
    {
        $balances = Balance::query()
            ->where('user_id', $this->user->id)
            ->orderBy('is_primary', 'desc')
            ->orderBy('name')
            ->get();

        $ids = $balances->pluck('id')->all();
        $reservedById = GetBalanceInsight::reservedByBalanceId($ids);

        $balanceRows = [];
        $discrepanciesCount = 0;
        $fixedCount = 0;

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
                if (! $this->isDryRun) {
                    SyncBalance::run($b);
                    $fixedCount++;
                    $status = 'FIXED';
                } else {
                    $status = 'DISCREPANCY';
                }
            } else {
                $status = 'MATCH';
            }

            $reserved = (int) ($reservedById[$b->id] ?? 0);
            $real = $expectedFinal - $reserved;

            $balanceRows[] = [
                'id' => $b->id,
                'name' => $b->name,
                'is_primary' => (bool) $b->is_primary,
                'stored_final' => $currentFinal,
                'computed_final' => $expectedFinal,
                'reserved' => $reserved,
                'real_balance' => $real,
                'status' => $status,
            ];
        }

        // Budget Envelopes Audit
        $zeroPlanned = BudgetItem::query()
            ->join('budgets', 'budgets.id', '=', 'budget_items.budget_id')
            ->where('budgets.user_id', $this->user->id)
            ->where('budget_items.planned_amount', 0)
            ->select('budget_items.*')
            ->get();

        $prunedCount = 0;
        $activeUnplannedCount = 0;

        foreach ($zeroPlanned as $item) {
            $linkedTxnCount = Transaction::query()->where('budget_item_id', $item->id)->count();

            if ($linkedTxnCount === 0) {
                if ($this->pruneZeroBudgetItems && ! $this->isDryRun) {
                    $item->delete();
                    $prunedCount++;
                }
            } else {
                $activeUnplannedCount++;
            }
        }

        $orphanedCount = $zeroPlanned->count() - $activeUnplannedCount;

        // Sinking Funds Audit
        $funds = SinkingFund::query()
            ->where('user_id', $this->user->id)
            ->with(['sourceBalance'])
            ->get();

        $fundRows = [];
        foreach ($funds as $f) {
            $progress = GetFundProgress::run($f);
            $fundRows[] = [
                'id' => $f->id,
                'name' => $f->name,
                'source_account' => $f->sourceBalance?->name ?? 'None',
                'accumulated' => (int) $progress['accumulated'],
                'target' => (int) $f->target_amount,
                'percent' => $progress['percent'],
                'next_due' => $f->next_due ? $f->next_due->toDateString() : 'None',
            ];
        }

        return [
            'user' => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'email' => $this->user->email,
            ],
            'balances' => $balanceRows,
            'discrepancies_count' => $discrepanciesCount,
            'fixed_count' => $fixedCount,
            'budget_items' => [
                'total_zero_planned' => $zeroPlanned->count(),
                'active_unplanned' => $activeUnplannedCount,
                'orphaned' => $orphanedCount,
                'pruned' => $prunedCount,
            ],
            'sinking_funds' => $fundRows,
        ];
    }
}
