<?php

namespace App\Actions;

use App\Enums\CategoryType;
use App\Models\Budget;
use App\Models\BudgetItem;
use App\Models\Transaction;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonImmutable;

class GetExpenseBreakdown extends Action
{
    public readonly User $user;

    private readonly Budget $activeBudget;

    public function __construct(User|int $user)
    {
        $this->user = $user instanceof User
            ? $user
            : User::query()->findOrFail($user);

        $this->activeBudget = Budget::query()
            ->where('user_id', $this->user->id)
            ->where('is_active', true)
            ->first();
    }

    public function handle(): array
    {
        if (! $this->activeBudget) {
            return [];
        }

        [$start, $end] = $this->getCurrentCycleRange();

        $budgetItems = BudgetItem::query()
            ->with('category')
            ->where('budget_id', $this->activeBudget->id)
            ->where('type', CategoryType::EXPENSE)
            ->get();

        $expenses = Transaction::query()
            ->selectRaw('budget_item_id, SUM(amount) as total_amount')
            ->where('user_id', $this->user->id)
            ->where('budget_id', $this->activeBudget->id)
            ->where('type', CategoryType::EXPENSE)
            ->whereBetween('date', [$start, $end])
            ->groupBy('budget_item_id')
            ->pluck('total_amount', 'budget_item_id');

        $totalExpense = $expenses->sum();

        if ($totalExpense <= 0) {
            return [];
        }

        return $budgetItems
            ->map(function ($budgetItem) use ($expenses, $totalExpense) {
                $amount = (int) ($expenses[$budgetItem->id] ?? 0);
                $percentage = round(($amount / $totalExpense) * 100, 2);

                return [
                    'category' => $budgetItem->category->name,
                    'amount' => $amount,
                    'percentage' => $percentage,
                ];
            })
            ->filter(fn ($item) => $item['amount'] > 0)
            ->sortByDesc(fn ($item) => $item['amount'])
            ->values()
            ->toArray();
    }

    private function getCutoffDateForMonth(CarbonImmutable $date, int $cutoffDay): CarbonImmutable
    {
        $lastDayOfMonth = $date->daysInMonth;
        $resolvedDay = min($cutoffDay, $lastDayOfMonth);

        return $date->setDay($resolvedDay)->startOfDay();
    }

    private function getCurrentCycleRange(): array
    {
        $now = Carbon::now()->toImmutable();
        $cutoffDay = $this->activeBudget->cutoff_day;

        $cutoffThisMonth = $this->getCutoffDateForMonth($now, $cutoffDay);

        if ($now->lte($cutoffThisMonth->endOfDay())) {
            $cutoffLastMonth = $this->getCutoffDateForMonth(
                $now->subMonthNoOverflow(),
                $cutoffDay
            );

            return [
                $cutoffLastMonth->addDay()->startOfDay(),
                $cutoffThisMonth->endOfDay(),
            ];
        }

        $cutoffNextMonth = $this->getCutoffDateForMonth(
            $now->addMonthNoOverflow(),
            $cutoffDay
        );

        return [
            $cutoffThisMonth->addDay()->startOfDay(),
            $cutoffNextMonth->endOfDay(),
        ];
    }
}
