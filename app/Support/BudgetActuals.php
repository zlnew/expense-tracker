<?php

namespace App\Support;

use App\Enums\CategoryType;
use App\Models\Budget;
use App\Models\FundContribution;
use App\Models\Transaction;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * THE single source of truth for envelope-aware budget actuals
 * (spec 2026-08-16 funds-envelope-budget, mechanism A — query layer only).
 *
 * Budget actuals = real transactions in the window, MINUS fund payout
 * transactions (excluded by id via fund_contributions.transaction_id),
 * PLUS fund set-asides whose fund's category maps to a budget item.
 *
 * Every budget-plane consumer (GetBudgetProgress, GetExpenseBreakdown,
 * CheckBudgetAlerts, BudgetRollover, BudgetApiController, the web
 * BudgetTransactionsController) MUST go through this class — do not inline
 * the formula anywhere else.
 */
final class BudgetActuals
{
    /**
     * All payout transaction ids for the user (ledger rows with a
     * transaction link). NOT scoped to non-deleted funds — deleting a fund
     * must not retroactively re-spike past budgets. The ledger is
     * append-only (no edit/delete endpoints), so the link is write-once.
     *
     * @return array<int>
     */
    public static function payoutTransactionIds(User $user): array
    {
        return FundContribution::query()
            ->where('user_id', $user->id)
            ->whereNotNull('transaction_id')
            ->pluck('transaction_id')
            ->all();
    }

    /**
     * Set-aside sums per budget item in the RAW date window [start, end].
     * Join: fund_contributions -> sinking_funds (fund_id, NOT soft-deleted)
     * -> budget_items (category_id = sinking_funds.category_id,
     * budget_id = $budget->id, type = EXPENSE).
     *
     * @return array<int, int> // budget_item_id => amount
     */
    public static function reservedPerItem(User $user, Budget $budget, CarbonImmutable $start, CarbonImmutable $end): array
    {
        return FundContribution::query()
            ->join('sinking_funds', 'sinking_funds.id', '=', 'fund_contributions.fund_id')
            ->join('budget_items', function ($join) use ($budget) {
                $join->on('budget_items.category_id', '=', 'sinking_funds.category_id')
                    ->where('budget_items.budget_id', '=', $budget->id)
                    ->where('budget_items.type', '=', CategoryType::EXPENSE->value);
            })
            ->whereNull('sinking_funds.deleted_at')
            ->where('fund_contributions.user_id', $user->id)
            ->where('fund_contributions.type', 'contribution')
            ->whereBetween('fund_contributions.date', [$start, $end])
            ->selectRaw('budget_items.id as budget_item_id, SUM(fund_contributions.amount) as total')
            ->groupBy('budget_items.id')
            ->pluck('total', 'budget_item_id')
            ->map(fn ($value) => (int) $value)
            ->all();
    }

    /**
     * Envelope-aware actuals per budget item in [start, end]:
     *   SUM(transactions per item)  − payout transactions (excluded by id)
     *                                + reservedPerItem(...)
     * No transaction-type filter: consumers whose item set is expense-only
     * (GetBudgetProgress, GetExpenseBreakdown, CheckBudgetAlerts, Rollover)
     * simply never read income keys; BudgetApiController needs income sums
     * for income items and this matches its current behaviour.
     * NOTE: withdrawal rows contribute NOTHING here — they exist only as
     * the exclusion key. Never subtract withdrawal amounts on top of the
     * id-exclusion; that would double-count the payout.
     *
     * @return array<int, int> // budget_item_id => actual
     */
    public static function perItem(User $user, Budget $budget, CarbonImmutable $start, CarbonImmutable $end): array
    {
        $payoutIds = self::payoutTransactionIds($user);

        $spent = Transaction::query()
            ->leftJoin('budget_items', function ($join) use ($budget) {
                $join->on('budget_items.category_id', '=', 'transactions.category_id')
                    ->where('budget_items.budget_id', '=', $budget->id);
            })
            ->selectRaw('COALESCE(transactions.budget_item_id, budget_items.id) as resolved_item_id, SUM(transactions.amount) as total_amount')
            ->where('transactions.user_id', $user->id)
            ->where(function ($q) use ($budget) {
                $q->where('transactions.budget_id', $budget->id)
                    ->orWhereNull('transactions.budget_id');
            })
            ->whereBetween('transactions.date', [$start, $end])
            ->excludeInternalTransfers()
            ->when($payoutIds !== [], fn ($query) => $query->whereNotIn('transactions.id', $payoutIds))
            ->groupBy('resolved_item_id')
            ->pluck('total_amount', 'resolved_item_id')
            ->map(fn ($value) => (int) $value)
            ->all();

        $reserved = self::reservedPerItem($user, $budget, $start, $end);

        $itemIds = array_filter(
            array_unique(array_merge(array_keys($spent), array_keys($reserved))),
            fn ($id) => ! empty($id)
        );

        $actuals = [];

        foreach ($itemIds as $itemId) {
            $actuals[(int) $itemId] = ($spent[$itemId] ?? 0) + ($reserved[$itemId] ?? 0);
        }

        return $actuals;
    }

    /**
     * reservedPerItem windowed by the budget's CUTOFF CYCLE month/year
     * (BudgetDetail's month view is cycle-based). Mirrors
     * GetMonthlySpendingTrend::buildTrendQuery's fromSub pattern: inner
     * subquery selects the contribution rows plus
     * BudgetCycle::cycleDateSql('fund_contributions.date', '?') bound with
     * $budget->cutoff_day as cycle_date, outer query filters
     * EXTRACT(MONTH/YEAR FROM cycle_date) = $month/$year and groups by item.
     *
     * @return array<int, int> // budget_item_id => amount
     */
    public static function reservedPerItemForCycleMonth(User $user, Budget $budget, int $month, int $year): array
    {
        $cycleDateSql = BudgetCycle::cycleDateSql('fund_contributions.date', '?');

        $subquery = FundContribution::query()
            ->join('sinking_funds', 'sinking_funds.id', '=', 'fund_contributions.fund_id')
            ->join('budget_items', function ($join) use ($budget) {
                $join->on('budget_items.category_id', '=', 'sinking_funds.category_id')
                    ->where('budget_items.budget_id', '=', $budget->id)
                    ->where('budget_items.type', '=', CategoryType::EXPENSE->value);
            })
            ->whereNull('sinking_funds.deleted_at')
            ->where('fund_contributions.user_id', $user->id)
            ->where('fund_contributions.type', 'contribution')
            ->selectRaw(
                "fund_contributions.amount, budget_items.id as budget_item_id, {$cycleDateSql} AS cycle_date",
                [$budget->cutoff_day],
            );

        // Plain DB builder: the Eloquent scopes against the aliased subquery
        // would mis-scope on Postgres (same reason as the trend query).
        return DB::query()
            ->fromSub($subquery, 'fc')
            ->selectRaw('budget_item_id, SUM(amount) AS total')
            ->whereRaw(BudgetCycle::extractMonthSql('cycle_date').' = ?', [$month])
            ->whereRaw(BudgetCycle::extractYearSql('cycle_date').' = ?', [$year])
            ->groupBy('budget_item_id')
            ->pluck('total', 'budget_item_id')
            ->map(fn ($value) => (int) $value)
            ->all();
    }
}
