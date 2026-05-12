<?php

namespace App\Actions;

use App\DTO\BudgetItemsData;
use App\Models\Budget;
use App\Models\BudgetItem;
use Illuminate\Support\Facades\DB;

class SaveBudgetItems extends Action
{
    public function __construct(
        private readonly Budget $budget,
        private readonly BudgetItemsData $data,
    ) {}

    public function handle(): void
    {
        DB::transaction(function () {
            $items = $this->data->items;

            foreach ($items as $item) {
                $budgetItem = $item->id ? BudgetItem::query()->findOrFail($item->id) : new BudgetItem;

                $budgetItem->fill([
                    'type' => $item->type,
                    'planned_amount' => $item->planned_amount,
                ]);

                $budgetItem->budget()->associate($this->budget);
                $budgetItem->category()->associate($item->category_id);

                $budgetItem->save();

                SyncBudgetItemAmounts::run($budgetItem);
            }
        });
    }
}
