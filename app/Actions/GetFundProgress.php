<?php

namespace App\Actions;

use App\Models\SinkingFund;
use Carbon\CarbonImmutable;

/**
 * Single source of truth for sinking-fund progress math (spec §3.3, D5).
 *
 * Derived fields are never stored — every web + API surface computes them
 * here so the numbers can't drift:
 * - accumulated = SUM(contribution) − SUM(withdrawal) over non-deleted rows;
 * - percent = accumulated / target_amount (rounded int for display);
 * - auto_contribution = monthly spread suggestion (D5);
 * - last_contribution_date = most recent contribution row;
 * - status = deterministic precedence: due_soon > overfunded > underfunded
 *   > on_track. due_soon wins over everything so an overfunded fund that is
 *   due next week still says "pay it".
 *
 * @return array{accumulated: int, percent: int, status: string, auto_contribution: int, last_contribution_date: CarbonImmutable|null}
 */
class GetFundProgress extends Action
{
    public function __construct(
        private readonly SinkingFund $fund,
        private readonly ?CarbonImmutable $today = null,
    ) {}

    public function handle(): array
    {
        $today = $this->today ?? CarbonImmutable::now()->startOfDay();

        // Date-scoped (Bug fix): future-dated ledger rows must not count
        // toward today's accumulated reserve — the reserve check in
        // PayFromFund and the progress display both read through here.
        $accumulated = (int) $this->fund->contributions()
            ->whereDate('date', '<=', $today)
            ->selectRaw(
                'COALESCE(SUM(CASE WHEN type = ? THEN amount ELSE 0 END), 0) - COALESCE(SUM(CASE WHEN type = ? THEN amount ELSE 0 END), 0) AS balance',
                ['contribution', 'withdrawal'],
            )
            ->value('balance');

        $percent = $this->fund->target_amount > 0
            ? (int) round(($accumulated / $this->fund->target_amount) * 100)
            : 0;

        $lastContributionDate = $this->fund->contributions()
            ->where('type', 'contribution')
            ->whereDate('date', '<=', $today)
            ->max('date');

        return [
            'accumulated' => $accumulated,
            'percent' => $percent,
            'status' => $this->status($accumulated, $percent, $today),
            'auto_contribution' => $this->autoContribution($accumulated, $today),
            'last_contribution_date' => $lastContributionDate
                ? CarbonImmutable::parse($lastContributionDate)
                : null,
        ];
    }

    /**
     * D5: spread the shortfall monthly until next_due. Overdue → full
     * catch-up suggestion; null next_due → assume a 1-year runway. A fixed
     * contribution_amount always wins over the suggestion (handled by the
     * caller choosing the fixed value when present).
     */
    private function autoContribution(int $accumulated, CarbonImmutable $today): int
    {
        $remaining = max(0, $this->fund->target_amount - $accumulated);

        if ($remaining === 0) {
            return 0;
        }

        $nextDue = $this->fund->next_due;

        if ($nextDue === null) {
            return max(1, (int) ceil($remaining / 12));
        }

        // Ceil the month count so a partial month still spreads the
        // shortfall across an extra cycle (45 days to due → 2 slices,
        // not "pay the whole shortfall now").
        $monthsToDue = max(0, (int) ceil($today->diffInMonths(CarbonImmutable::parse($nextDue)->startOfDay())));

        return max(1, (int) ceil($remaining / max(1, $monthsToDue)));
    }

    /**
     * §3.3 precedence, evaluated top-down.
     */
    private function status(int $accumulated, int $percent, CarbonImmutable $today): string
    {
        $nextDue = $this->fund->next_due
            ? CarbonImmutable::parse($this->fund->next_due)->startOfDay()
            : null;

        if ($nextDue !== null && $nextDue->lte($today->addDays(30))) {
            return 'due_soon';
        }

        if ($accumulated >= $this->fund->target_amount) {
            return 'overfunded';
        }

        if ($percent < 80 && $nextDue !== null && $nextDue->lte($today->addDays(60))) {
            return 'underfunded';
        }

        return 'on_track';
    }
}
