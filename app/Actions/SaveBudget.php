<?php

namespace App\Actions;

use App\DTO\BudgetData;
use App\Models\Budget;
use App\Models\BudgetItem;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SaveBudget extends Action
{
    public function __construct(
        private readonly Budget $budget,
        private readonly BudgetData $data,
    ) {}

    public function handle(): Budget
    {
        return DB::transaction(function () {
            $this->budget->fill([
                'period_start' => $this->data->period_start,
                'period_end' => $this->data->period_end,
                'notes' => $this->data->notes,
            ]);

            if (! $this->budget->user_id) {
                $this->budget->user()->associate(Auth::id());
            }

            $this->budget->save();

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
            }

            return $this->budget;
        });
    }
}
