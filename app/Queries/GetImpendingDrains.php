<?php

namespace App\Queries;

use App\Enums\CategoryType;
use App\Models\Balance;
use App\Models\RecurringTransaction;
use App\Models\SinkingFund;
use Carbon\CarbonImmutable;

/**
 * US-3: impending drains within a configurable horizon (default 60 days).
 *
 * - Lists each upcoming sinking-fund contribution due (from_balance sourced,
 *   contribution_amount | auto_contribution) + each upcoming recurring
 *   transaction occurrence: amount, source balance, due/run date.
 * - Totals: total_impending_outflow and per-balance projected_free_after
 *   = Real − impending (Real from GetBalanceInsight).
 * - Warns when impending would push a balance's Real negative
 *   (projected_free_after < 0).
 *
 * Horizon is inclusive (today..today+window). Overdue fund dues are included
 * (they sit at next_due <= window end). Only active recurrings count; each
 * recurring contributes at most ONE pending occurrence (its next_run_date) if
 * that date falls inside the window — we do not fan out hypothetical future
 * cycles.
 *
 * @return array{
 *   window_days: int,
 *   from: string,
 *   until: string,
 *   total_impending_outflow: int,
 *   items: array<int, array{kind: string, id: int, label: string, amount: int, balance_id: int, balance_name: string, due_date: string, source: string}>,
 *   per_balance: array<int, array{balance_id: int, balance_name: string, real: int, impending: int, projected_free_after: int, would_go_negative: bool}>,
 *   has_negative_warning: bool
 * }
 */
class GetImpendingDrains extends Query
{
    public function __construct(
        private readonly int $userId,
        private readonly int $windowDays = 60,
        private readonly ?CarbonImmutable $today = null,
    ) {}

    public function handle(): array
    {
        $today = ($this->today ?? CarbonImmutable::now())->startOfDay();
        $until = $today->addDays(max(0, $this->windowDays));

        // Funds due inside the window (including overdue).
        $funds = SinkingFund::query()
            ->where('user_id', $this->userId)
            ->whereNotNull('next_due')
            ->whereNotNull('from_balance_id')
            ->where('next_due', '<=', $until->toDateString())
            ->with(['category', 'contributions'])
            ->orderBy('next_due')
            ->get();

        // Active recurrings whose next_run_date falls inside [today, until].
        // Only expense recurrings drain a balance.
        $recurrings = RecurringTransaction::query()
            ->where('user_id', $this->userId)
            ->where('is_active', true)
            ->where('type', CategoryType::EXPENSE)
            ->where('next_run_date', '>=', $today->toDateString())
            ->where('next_run_date', '<=', $until->toDateString())
            ->with(['balance'])
            ->orderBy('next_run_date')
            ->get();

        $items = collect();
        $impendingByBalance = []; // balance_id => sum

        foreach ($funds as $fund) {
            $progress = GetFundProgress::run($fund, $today);
            $amount = $fund->contribution_amount ?? $progress['auto_contribution'];
            $amount = (int) $amount;
            if ($amount <= 0) {
                continue;
            }

            $dueDate = CarbonImmutable::parse($fund->next_due)->startOfDay();
            $balanceId = (int) $fund->from_balance_id;
            $intervalMonths = max(1, (int) ($fund->due_interval_months ?: match ($fund->cadence) {
                'yearly' => 12,
                'quarterly' => 3,
                default => 1,
            }));

            $curDue = $dueDate;
            $anchorDay = $fund->anchor_day ?? $dueDate->day;

            while ($curDue->lte($until)) {
                $items->push([
                    'kind' => 'fund_due',
                    'id' => $fund->id,
                    'label' => $fund->name,
                    'amount' => $amount,
                    'balance_id' => $balanceId,
                    'balance_name' => $fund->sourceBalance?->name ?? (string) $balanceId,
                    'due_date' => $curDue->toDateString(),
                    'source' => $fund->contribution_amount !== null ? 'fixed' : 'auto',
                ]);

                $impendingByBalance[$balanceId] = ($impendingByBalance[$balanceId] ?? 0) + $amount;

                // Advance by intervalMonths
                $targetMonth = $curDue->startOfMonth()->addMonthsNoOverflow($intervalMonths);
                $curDue = $targetMonth->setDay(min($anchorDay, $targetMonth->daysInMonth));
            }
        }

        foreach ($recurrings as $r) {
            $amount = (int) $r->amount;
            $balanceId = (int) $r->balance_id;
            $curDate = CarbonImmutable::parse($r->next_run_date)->startOfDay();

            while ($curDate->lte($until)) {
                if ($r->end_date && $curDate->gt($r->end_date)) {
                    break;
                }

                $items->push([
                    'kind' => 'recurring',
                    'id' => $r->id,
                    'label' => $r->description ?? ('Recurring #'.$r->id),
                    'amount' => $amount,
                    'balance_id' => $balanceId,
                    'balance_name' => $r->balance?->name ?? (string) $balanceId,
                    'due_date' => $curDate->toDateString(),
                    'source' => 'recurring:'.$r->frequency,
                ]);

                $impendingByBalance[$balanceId] = ($impendingByBalance[$balanceId] ?? 0) + $amount;

                $curDate = match ($r->frequency) {
                    'daily' => $curDate->addDay(),
                    'weekly' => $curDate->addWeek(),
                    'yearly' => $curDate->addYearNoOverflow(),
                    default => $curDate->addMonthNoOverflow(),
                };
            }
        }

        $items = $items->sortBy('due_date')->values();

        $total = (int) array_sum(array_values($impendingByBalance));

        // Per-balance projection: Real − impending.
        $balanceIds = array_keys($impendingByBalance);

        // Include balances that have no impending (so callers can show a full map).
        // We only return \"involved\" balances — those with non-zero impending —
        // plus we include has_negative_warning to simplify the frontend.
        $perBalance = [];
        $hasNegative = false;

        // Load balance rows for names and to drive GetBalanceInsight.
        $balances = Balance::query()->whereIn('id', $balanceIds)->get()->keyBy('id');

        foreach ($impendingByBalance as $bid => $imp) {
            $row = $balances->get($bid);
            $real = GetBalanceInsight::run($bid)['real']; // derived: active − reserved

            $freeAfter = $real - (int) $imp;
            $warn = $freeAfter < 0;
            $hasNegative = $hasNegative || $warn;

            $perBalance[] = [
                'balance_id' => $bid,
                'balance_name' => $row?->name ?? (string) $bid,
                'real' => (int) $real,
                'impending' => (int) $imp,
                'projected_free_after' => (int) $freeAfter,
                'would_go_negative' => $warn,
            ];
        }

        $perBalance = collect($perBalance)->sortBy('balance_id')->values()->all();

        return [
            'window_days' => $this->windowDays,
            'from' => $today->toDateString(),
            'until' => $until->toDateString(),
            'total_impending_outflow' => $total,
            'items' => $items->all(),
            'per_balance' => $perBalance,
            'has_negative_warning' => $hasNegative,
        ];
    }
}
