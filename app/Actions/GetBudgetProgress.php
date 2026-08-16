<?php

namespace App\Actions;

use App\DTO\BudgetItemData;
use App\Enums\CategoryType;
use App\Models\Budget;
use App\Models\BudgetItem;
use App\Models\User;
use App\Support\BudgetActuals;
use App\Support\BudgetCycle;
use Spatie\LaravelData\DataCollection;

class GetBudgetProgress extends Action
{
    public readonly User $user;

    private readonly ?Budget $activeBudget;

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

    public function handle(): DataCollection
    {
        if (! $this->activeBudget) {
            return new DataCollection(BudgetItemData::class, []);
        }

        [$start, $end] = BudgetCycle::currentCycleRange($this->activeBudget);

        $budgetItems = BudgetItem::query()
            ->with('category')
            ->where('budget_id', $this->activeBudget->id)
            ->where('type', CategoryType::EXPENSE)
            ->get();

        // Envelope-aware actuals: real expenses + fund set-asides in the
        // fund's category, minus budget-exempt fund payouts (BudgetActuals).
        $actuals = BudgetActuals::perItem($this->user, $this->activeBudget, $start, $end);

        $progress = $budgetItems
            ->sortBy(fn ($bi) => $bi->category?->name)
            ->map(function ($bi) use ($actuals) {
                $actualAmount = $actuals[$bi->id] ?? 0;

                return BudgetItemData::from([
                    'id' => $bi->id,
                    'budget_id' => $bi->budget_id,
                    'category_id' => $bi->category_id,
                    'type' => $bi->type,
                    'planned_amount' => $bi->planned_amount,
                    'actual_amount' => $actualAmount,
                    'diff_amount' => $bi->planned_amount - $actualAmount,
                    'category' => $bi->category,
                ]);
            })->values();

        return BudgetItemData::collect($progress, DataCollection::class);
    }
}
