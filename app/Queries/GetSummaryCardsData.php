<?php

namespace App\Queries;

use App\Enums\CategoryType;
use App\Models\Balance;
use App\Models\Budget;
use App\Models\BudgetItem;
use App\Models\Transaction;
use App\Models\User;
use App\Support\BalancePresenter;
use App\Support\BudgetCycle;
use Carbon\CarbonImmutable;

class GetSummaryCardsData extends Query
{
    public readonly User $user;

    private readonly ?Budget $activeBudget;

    private readonly ?CarbonImmutable $today;

    public function __construct(User|int $user, ?CarbonImmutable $today = null)
    {
        $this->user = $user instanceof User
            ? $user
            : User::query()->findOrFail($user);

        $this->today = $today;

        $this->activeBudget = Budget::query()
            ->where('user_id', $this->user->id)
            ->where('is_active', true)
            ->first();
    }

    /**
     * @return array{total_balance:int,total_active:int,total_reserved:int,current_month_expenses:int,current_month_incomes:int,budget_remaining:int,has_active_budget:bool,active_budget_id:?int}
     */
    public function handle(): array
    {
        [$totalBalance, $totalActive, $totalReserved] = $this->getBalanceTotals();
        $currentMonthExpenses = $this->getCurrentMonthExpenses();
        $currentMonthIncomes = $this->getCurrentMonthIncomes();
        $budgetRemaining = $this->getPlannedBudget() - $currentMonthExpenses;

        [$start, $end] = BudgetCycle::currentCycleRange($this->activeBudget);

        return [
            // Contract: total_balance = Σ Real (headline). Also expose the
            // composed legs so the UI can show the breakdown.
            'total_balance' => $totalBalance,
            'total_active' => $totalActive,
            'total_reserved' => $totalReserved,
            'current_month_expenses' => $currentMonthExpenses,
            'current_month_incomes' => $currentMonthIncomes,
            'budget_remaining' => $budgetRemaining,
            'has_active_budget' => $this->activeBudget !== null,
            'active_budget_id' => $this->activeBudget?->id,
            'cycle_start' => $start->toDateString(),
            'cycle_end' => $end->toDateString(),
            'cutoff_day' => $this->activeBudget?->cutoff_day,
        ];
    }

    /**
     * @return array{0:int,1:int,2:int} [netWorthReal, totalActive, totalReserved]
     */
    private function getBalanceTotals(): array
    {
        // Net worth = Σ Real (Active − Reserved) — spec §7.1 / US-1.
        // Reserved comes from SinkingFund reserves anchored to each balance.
        $today = $this->today ?? CarbonImmutable::now()->startOfDay();

        $balances = Balance::query()
            ->where('user_id', $this->user->id)
            ->get(['id', 'final_amount', 'initial_amount']);

        if ($balances->isEmpty()) {
            return [0, 0, 0];
        }

        $ids = $balances->pluck('id')->all();
        $reservedById = BalancePresenter::reservedByBalanceId($ids, $today);

        $totalActive = 0;
        $totalReserved = 0;

        foreach ($balances as $b) {
            $active = (int) ($b->final_amount ?? $b->initial_amount ?? 0);
            $reserved = (int) ($reservedById[$b->id] ?? 0);
            $totalActive += $active;
            $totalReserved += $reserved;
        }

        return [$totalActive - $totalReserved, $totalActive, $totalReserved];
    }

    private function getCurrentMonthExpenses(): int
    {
        if (! $this->activeBudget) {
            return 0;
        }

        [$start, $end] = BudgetCycle::currentCycleRange($this->activeBudget);

        return (int) Transaction::query()
            ->where('user_id', $this->user->id)
            ->where('budget_id', $this->activeBudget->id)
            ->where('type', CategoryType::EXPENSE)
            ->excludeInternalTransfers()
            ->whereBetween('date', [$start, $end])
            ->sum('amount');
    }

    private function getCurrentMonthIncomes(): int
    {
        if (! $this->activeBudget) {
            return 0;
        }

        [$start, $end] = BudgetCycle::currentCycleRange($this->activeBudget);

        return (int) Transaction::query()
            ->where('user_id', $this->user->id)
            ->where('budget_id', $this->activeBudget->id)
            ->where('type', CategoryType::INCOME)
            ->excludeInternalTransfers()
            ->whereBetween('date', [$start, $end])
            ->sum('amount');
    }

    private function getPlannedBudget(): int
    {
        if (! $this->activeBudget) {
            return 0;
        }

        return (int) BudgetItem::query()
            ->where('budget_id', $this->activeBudget->id)
            ->where('type', CategoryType::EXPENSE)
            ->sum('planned_amount');
    }
}
