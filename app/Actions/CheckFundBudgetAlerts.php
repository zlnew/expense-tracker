<?php

namespace App\Actions;

use App\Enums\CategoryType;
use App\Models\Budget;
use App\Models\BudgetItem;
use App\Models\FundContribution;
use App\Models\SinkingFund;
use App\Support\BudgetCycle;

/**
 * Fire budget alerts from a fund set-aside — the reservation IS the budget
 * movement (envelope-basis, 2026-08-16 spec). A category that is fully
 * enveloped (set-asides + an exempt payout) would never alert if only real
 * transactions fired, so set-asides evaluate the same thresholds.
 *
 * Guards, in order: fund has a category; user has a webhook; an active
 * budget exists; a matching expense item exists; the contribution's date is
 * inside the active budget's current cycle (a back-dated set-aside must not
 * evaluate this cycle). Delegates to CheckBudgetAlerts::evaluate.
 */
class CheckFundBudgetAlerts extends Action
{
    public function __construct(
        private readonly SinkingFund $fund,
        private readonly FundContribution $contribution,
    ) {}

    public function handle(): void
    {
        if ($this->fund->category_id === null) {
            return;
        }

        $user = $this->fund->user;

        if (! $user->discord_webhook_url) {
            return;
        }

        $budget = Budget::query()
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->first();

        if (! $budget) {
            return;
        }

        $item = BudgetItem::query()
            ->where('budget_id', $budget->id)
            ->where('category_id', $this->fund->category_id)
            ->where('type', CategoryType::EXPENSE)
            ->first();

        if (! $item) {
            return;
        }

        [$start, $end] = BudgetCycle::currentCycleRange($budget);

        if ($this->contribution->date->lt($start) || $this->contribution->date->gt($end)) {
            return;
        }

        CheckBudgetAlerts::evaluate($user, $item);
    }
}
