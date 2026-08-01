<?php

namespace App\Support;

use App\Models\Budget;
use Carbon\Carbon;
use Carbon\CarbonImmutable;

/**
 * Single source of truth for budget cutoff-cycle math.
 *
 * A budget uses a "cutoff day" (e.g. the 25th): transactions dated after the
 * cutoff belong to the NEXT cycle. All dashboard widgets, list filters and
 * trends must agree on this rule — use these helpers instead of reimplementing
 * the logic (it was previously copy-pasted across 5 files with drift).
 */
class BudgetCycle
{
    /**
     * Resolve the cutoff date for a given month, clamping to the last day of
     * the month when the cutoff day doesn't exist (e.g. 31 in February).
     */
    public static function cutoffDateForMonth(CarbonImmutable $date, int $cutoffDay): CarbonImmutable
    {
        $lastDayOfMonth = $date->daysInMonth;
        $resolvedDay = min($cutoffDay, $lastDayOfMonth);

        return $date->setDay($resolvedDay)->startOfDay();
    }

    /**
     * Current cycle window [start, end] for an active budget, based on now().
     * Returns the current calendar month when no budget is active.
     *
     * @return array{CarbonImmutable, CarbonImmutable}
     */
    public static function currentCycleRange(?Budget $budget): array
    {
        if (! $budget) {
            $now = Carbon::now()->toImmutable();

            return [$now->startOfMonth(), $now->endOfMonth()];
        }

        $now = Carbon::now()->toImmutable();
        $cutoffDay = $budget->cutoff_day;

        $cutoffThisMonth = self::cutoffDateForMonth($now, $cutoffDay);

        if ($now->lte($cutoffThisMonth->endOfDay())) {
            $cutoffLastMonth = self::cutoffDateForMonth(
                $now->subMonthNoOverflow(),
                $cutoffDay
            );

            return [
                $cutoffLastMonth->addDay()->startOfDay(),
                $cutoffThisMonth->endOfDay(),
            ];
        }

        $cutoffNextMonth = self::cutoffDateForMonth(
            $now->addMonthNoOverflow(),
            $cutoffDay
        );

        return [
            $cutoffThisMonth->addDay()->startOfDay(),
            $cutoffNextMonth->endOfDay(),
        ];
    }

    /**
     * SQL fragment computing the cycle date of a transaction column.
     *
     * Works wherever the transaction table is in scope. $cutoffExpression is
     * how the budget cutoff_day is referenced — a joined column
     * ('budgets.cutoff_day') or a bound placeholder ('?').
     */
    public static function cycleDateSql(string $dateColumn, string $cutoffExpression): string
    {
        $effectiveCutoff = self::effectiveCutoffSql($dateColumn, $cutoffExpression);

        return "
            CASE
                WHEN EXTRACT(DAY FROM {$dateColumn}) > ({$effectiveCutoff})
                    THEN {$dateColumn} + INTERVAL '1 month'
                ELSE {$dateColumn}
            END
        ";
    }

    /**
     * SQL fragment for the effective cutoff day of a month (clamped to the
     * last day when the configured cutoff exceeds the month length).
     */
    public static function effectiveCutoffSql(string $dateColumn, string $cutoffExpression): string
    {
        return "LEAST(
            {$cutoffExpression},
            EXTRACT(DAY FROM (DATE_TRUNC('month', {$dateColumn}) + INTERVAL '1 month' - INTERVAL '1 day'))
        )";
    }
}
