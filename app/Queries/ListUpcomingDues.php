<?php

namespace App\Queries;

use App\Models\SinkingFund;
use Carbon\CarbonImmutable;

/**
 * Funds with a next_due inside the horizon (default 60 days) OR overdue,
 * with the derived fields the "upcoming" surface needs:
 *
 * - days_until_due: negative when overdue;
 * - projected_shortfall = max(0, target − accumulated − expected_contributions_before_due),
 *   where expected_contributions_before_due = (fixed | auto) × months_to_due.
 *   A fund that's behind today but fine at its current slice shows no
 *   shortfall — same formula as GetFundProgress' auto_contribution.
 *
 * @return array<int, array{fund: SinkingFund, days_until_due: int, projected_shortfall: int}>
 */
class ListUpcomingDues extends Query
{
    public function __construct(
        private readonly int $userId,
        private readonly int $horizonDays = 60,
        private readonly ?CarbonImmutable $today = null,
    ) {}

    public function handle(): array
    {
        $today = ($this->today ?? CarbonImmutable::now())->startOfDay();
        $horizonEnd = $today->addDays($this->horizonDays);

        $funds = SinkingFund::query()
            ->with('category')
            ->where('user_id', $this->userId)
            ->whereNotNull('next_due')
            ->where('next_due', '<=', $horizonEnd->toDateString())
            ->orderBy('next_due')
            ->get();

        return $funds
            ->map(function (SinkingFund $fund) use ($today) {
                $nextDue = CarbonImmutable::parse($fund->next_due)->startOfDay();

                $progress = GetFundProgress::run($fund, $today);

                $monthlySlice = $fund->contribution_amount ?? $progress['auto_contribution'];

                $monthsToDue = max(0, (int) $today->diffInMonths($nextDue));
                $expectedBeforeDue = $monthlySlice * $monthsToDue;

                return [
                    'fund' => $fund,
                    'days_until_due' => (int) $today->diffInDays($nextDue),
                    'projected_shortfall' => max(0, $fund->target_amount - $progress['accumulated'] - $expectedBeforeDue),
                ];
            })
            ->values()
            ->all();
    }
}
