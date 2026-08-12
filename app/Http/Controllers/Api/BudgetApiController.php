<?php

namespace App\Http\Controllers\Api;

use App\DTO\BudgetData;
use App\DTO\BudgetItemData;
use App\Http\Controllers\Controller;
use App\Models\Budget;
use App\Models\BudgetItem;
use App\Models\Transaction;
use App\Queries\BudgetQuery;
use App\Support\BudgetCycle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\LaravelData\DataCollection;

class BudgetApiController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $budgets = BudgetQuery::make($request->all(), ['items.category'])
            ->forUser($request->user()->id)
            ->get();

        $result = $budgets->map(fn (Budget $budget) => $this->toData($budget));

        return response()->json($result);
    }

    /**
     * Build the budget payload with per-item spent-to-date (actual_amount /
     * diff_amount) computed live, matching the dashboard's GetBudgetProgress
     * semantics. The budget_items table columns are legacy dead columns —
     * the app never persists these values, so reading them would always
     * report 0 spent.
     */
    private function toData(Budget $budget): BudgetData
    {
        $totals = $this->spentTotalsByItem($budget);

        $items = $budget->items
            ->map(function (BudgetItem $item) use ($totals): BudgetItemData {
                $actual = (int) ($totals[$item->id] ?? 0);

                return BudgetItemData::from([
                    'id' => $item->id,
                    'budget_id' => $item->budget_id,
                    'category_id' => $item->category_id,
                    'type' => $item->type,
                    'planned_amount' => $item->planned_amount,
                    'actual_amount' => $actual,
                    'diff_amount' => $item->planned_amount - $actual,
                    'category' => $item->category,
                ]);
            })
            ->values();

        return BudgetData::from([
            'id' => $budget->id,
            'user_id' => $budget->user_id,
            'period_start' => $budget->period_start,
            'period_end' => $budget->period_end,
            'cutoff_day' => $budget->cutoff_day,
            'is_active' => $budget->is_active,
            'carry_over' => $budget->carry_over,
            'notes' => $budget->notes,
            'updated_at' => $budget->updated_at,
            'items' => BudgetItemData::collect($items, DataCollection::class),
        ]);
    }

    /**
     * Sum expenses per budget item for the budget's relevant window:
     * the current cutoff-aware cycle for the active budget (what the
     * dashboard shows), or the budget's own period for historical budgets.
     *
     * @return array<int, int> keyed by budget_item_id
     */
    private function spentTotalsByItem(Budget $budget): array
    {
        if ($budget->items->isEmpty()) {
            return [];
        }

        [$start, $end] = $budget->is_active
            ? BudgetCycle::currentCycleRange($budget)
            : [$budget->period_start, $budget->period_end];

        return Transaction::query()
            ->selectRaw('budget_item_id, SUM(amount) as total')
            ->where('user_id', $budget->user_id)
            ->where('budget_id', $budget->id)
            ->whereBetween('date', [$start, $end])
            ->groupBy('budget_item_id')
            ->pluck('total', 'budget_item_id')
            ->map(fn ($total) => (int) $total)
            ->all();
    }
}
