<?php

namespace App\Mcp\Resources;

use App\Actions\GetBudgetProgress;
use App\Models\Budget;
use App\Models\User;

class ActiveBudgetResource implements ResourceInterface
{
    public function uri(): string
    {
        return 'expense-tracker://budget/active';
    }

    public function name(): string
    {
        return 'Active Budget & Spending Limits';
    }

    public function description(): string
    {
        return 'Active budget period limits, current actuals, and category progress.';
    }

    public function mimeType(): string
    {
        return 'application/json';
    }

    public function read(User $user): string
    {
        $budget = Budget::query()->where('user_id', $user->id)->where('is_active', true)->first();
        if (! $budget) {
            return json_encode(['error' => 'No active budget']);
        }

        $items = GetBudgetProgress::run($user);
        $categories = [];
        foreach ($items as $item) {
            $categories[] = [
                'category_id' => $item->category_id,
                'category' => $item->category?->name,
                'planned' => $item->planned_amount,
                'actual' => $item->actual_amount ?? 0,
                'diff' => $item->diff_amount ?? ($item->planned_amount - ($item->actual_amount ?? 0)),
            ];
        }

        return json_encode([
            'budget_id' => $budget->id,
            'period_start' => $budget->period_start?->toDateString(),
            'period_end' => $budget->period_end?->toDateString(),
            'cutoff_day' => $budget->cutoff_day,
            'items' => $categories,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }
}
