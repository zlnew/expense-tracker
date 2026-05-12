<?php

namespace App\Actions;

use App\DTO\BudgetItemsData;
use App\Models\Budget;
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
            $items = collect($this->data->items);

            $ids = $items
                ->pluck('id')
                ->filter()
                ->values();

            $this->budget->items()
                ->whereNotIn('id', $ids)
                ->delete();

            foreach ($items as $item) {
                $this->budget->items()->updateOrCreate(
                    [
                        'id' => $item->id,
                    ],
                    [
                        'category_id' => $item->category_id,
                        'type' => $item->type,
                        'planned_amount' => $item->planned_amount,
                    ]
                );
            }
        });
    }
}
