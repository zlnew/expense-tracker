<?php

namespace App\Actions;

use App\DTO\BudgetData;
use App\Models\Budget;
use App\Models\BudgetItem;
use App\Support\BudgetRollover;
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
            // Track whether carry-over was already on, so we only roll the
            // leftover in once (first time it's enabled — create or edit).
            $wasCarryOver = (bool) $this->budget->getOriginal('carry_over');

            $this->budget->fill([
                'period_start' => $this->data->period_start,
                'period_end' => $this->data->period_end,
                'cutoff_day' => $this->data->cutoff_day,
                'notes' => $this->data->notes,
                'carry_over' => $this->data->carry_over,
            ]);

            if (! $this->budget->user_id) {
                $this->budget->user()->associate(Auth::id());
            }

            $this->budget->save();

            $items = $this->data->items;

            // YNAB-style rollover: when carry_over is first enabled (on a new
            // budget or by ticking the box on an existing one), each category
            // starts with the previous cycle's unused amount added to the
            // planned amount. Guarded by $wasCarryOver so a later edit of an
            // already-carrying budget never rolls the leftover in twice.
            if ($this->data->carry_over && ! $wasCarryOver) {
                $items = BudgetRollover::apply($this->budget, $items);
            }

            $submittedItemIds = [];

            foreach ($items as $item) {
                $budgetItem = $item->id ? BudgetItem::query()->findOrFail($item->id) : new BudgetItem;

                $budgetItem->fill([
                    'type' => $item->type,
                    'planned_amount' => $item->planned_amount,
                ]);

                $budgetItem->budget()->associate($this->budget);
                $budgetItem->category()->associate($item->category_id);

                $budgetItem->save();

                if ($budgetItem->id) {
                    $submittedItemIds[] = $budgetItem->id;
                }
            }

            // Prune budget items that existed before but were removed from the
            // form, so the budget never carries stale planned-amount rows.
            // (Empty submitted set => delete everything; Laravel's whereNotIn
            // with an empty array is a no-op, so branch explicitly.)
            // Items still referenced by transactions are kept — deleting them
            // would violate the FK, mirroring DeleteBudget's guard.
            $pruneQuery = $this->budget->items()->whereDoesntHave('transactions');

            if ($submittedItemIds !== []) {
                $pruneQuery->whereNotIn('id', $submittedItemIds);
            }

            $pruneQuery->delete();

            return $this->budget;
        });
    }
}
