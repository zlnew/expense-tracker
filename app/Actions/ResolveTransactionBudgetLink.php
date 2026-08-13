<?php

namespace App\Actions;

use App\Models\Budget;
use App\Models\BudgetItem;

/**
 * Resolve the budget link for a transaction being saved through the API,
 * mirroring the web create/update dialog (category -> item in the active
 * budget, honoring the budget's cutoff cycle).
 *
 * The web UI sends explicit budget_id + budget_item_id from the client;
 * API clients (Cogsworth) only send category_id. This fills the gap server
 * side so both paths produce the same rows: budget_id AND budget_item_id
 * set, which is what budget actual_amount sums on.
 *
 * Rules:
 * - explicit budget_item_id wins; when budget_id is missing it is derived
 *   from the item's own budget;
 * - otherwise, when category_id is given, look the item up in the explicit
 *   budget_id, or in the user's active budget when none was given;
 * - no category / no active budget -> nothing to link.
 */
class ResolveTransactionBudgetLink extends Action
{
    public function __construct(
        private readonly int $userId,
        private readonly ?int $categoryId,
        private readonly ?int $budgetId = null,
        private readonly ?int $budgetItemId = null,
    ) {}

    /**
     * @return array{budget_id: int|null, budget_item_id: int|null}
     */
    public function handle(): array
    {
        if ($this->budgetItemId !== null) {
            $budgetId = $this->budgetId ?? BudgetItem::query()
                ->where('id', $this->budgetItemId)
                ->value('budget_id');

            return ['budget_id' => $budgetId, 'budget_item_id' => $this->budgetItemId];
        }

        if ($this->categoryId === null) {
            return ['budget_id' => $this->budgetId, 'budget_item_id' => null];
        }

        $budgetId = $this->budgetId;

        if ($budgetId === null) {
            $budgetId = Budget::query()
                ->where('user_id', $this->userId)
                ->where('is_active', true)
                ->value('id');
        }

        if ($budgetId === null) {
            return ['budget_id' => null, 'budget_item_id' => null];
        }

        $itemId = BudgetItem::query()
            ->where('budget_id', $budgetId)
            ->where('category_id', $this->categoryId)
            ->value('id');

        return ['budget_id' => $budgetId, 'budget_item_id' => $itemId ?: null];
    }
}
