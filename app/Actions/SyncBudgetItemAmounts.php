<?php

namespace App\Actions;

use App\Models\BudgetItem;
use App\Models\Transaction;
use Illuminate\Support\Facades\Auth;

class SyncBudgetItemAmounts extends Action
{
    public readonly BudgetItem $budgetItem;

    public function __construct(BudgetItem|int $budgetItem)
    {
        $this->budgetItem = $budgetItem instanceof BudgetItem
            ? $budgetItem
            : BudgetItem::query()->findOrFail($budgetItem);
    }

    public function handle(): void
    {
        $userId = Auth::id();

        $transactions = Transaction::query()
            ->where('user_id', $userId)
            ->where('budget_id', $this->budgetItem->budget_id)
            ->where('budget_item_id', $this->budgetItem->id)
            ->where('category_id', $this->budgetItem->category_id)
            ->get();

        $plannedAmount = $this->budgetItem->planned_amount;
        $actualAmount = (int) $transactions->sum('amount');
        $diffAmount = $plannedAmount - $actualAmount;

        $this->budgetItem->actual_amount = $actualAmount;
        $this->budgetItem->diff_amount = $diffAmount;
        $this->budgetItem->save();
    }
}
