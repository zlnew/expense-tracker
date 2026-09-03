<?php

namespace App\Mcp\Tools;

use App\Actions\GetBudgetProgress;
use App\Models\Budget;
use App\Models\User;

class GetBudgetStatusTool implements ToolInterface
{
    public function name(): string
    {
        return 'get_budget_status';
    }

    public function description(): string
    {
        return 'Get status of the active budget: planned vs actual spending per category, remaining allowances, and overspend warnings.';
    }

    public function schema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'budget_id' => [
                    'type' => 'integer',
                    'description' => 'Optional specific budget ID (defaults to currently active budget)',
                ],
            ],
        ];
    }

    public function execute(User $user, array $arguments): array
    {
        $budget = null;
        if (! empty($arguments['budget_id'])) {
            $budget = Budget::query()->where('user_id', $user->id)->find($arguments['budget_id']);
        } else {
            $budget = Budget::query()->where('user_id', $user->id)->where('is_active', true)->first();
        }

        if (! $budget) {
            return [
                'content' => [['type' => 'text', 'text' => 'No active budget found for this user.']],
            ];
        }

        $items = GetBudgetProgress::run($user);

        $totalPlanned = 0;
        $totalActual = 0;
        $categories = [];

        foreach ($items as $item) {
            $planned = $item->planned_amount;
            $actual = $item->actual_amount ?? 0;
            $diff = $item->diff_amount ?? ($planned - $actual);

            $totalPlanned += $planned;
            $totalActual += $actual;

            $percent = $planned > 0 ? round(($actual / $planned) * 100, 1) : 0;

            $categories[] = [
                'category_id' => $item->category_id,
                'category_name' => $item->category?->name ?? 'Unknown',
                'planned' => $planned,
                'planned_formatted' => 'Rp '.number_format($planned, 0, ',', '.'),
                'actual' => $actual,
                'actual_formatted' => 'Rp '.number_format($actual, 0, ',', '.'),
                'remaining' => $diff,
                'remaining_formatted' => 'Rp '.number_format($diff, 0, ',', '.'),
                'percent_spent' => $percent.'%',
                'is_overspent' => $actual > $planned,
            ];
        }

        $totalRemaining = $totalPlanned - $totalActual;
        $overallPercent = $totalPlanned > 0 ? round(($totalActual / $totalPlanned) * 100, 1) : 0;

        $result = [
            'budget_id' => $budget->id,
            'period_start' => $budget->period_start?->toDateString(),
            'period_end' => $budget->period_end?->toDateString(),
            'cutoff_day' => $budget->cutoff_day,
            'is_active' => (bool) $budget->is_active,
            'total_planned' => $totalPlanned,
            'total_planned_formatted' => 'Rp '.number_format($totalPlanned, 0, ',', '.'),
            'total_actual' => $totalActual,
            'total_actual_formatted' => 'Rp '.number_format($totalActual, 0, ',', '.'),
            'total_remaining' => $totalRemaining,
            'total_remaining_formatted' => 'Rp '.number_format($totalRemaining, 0, ',', '.'),
            'overall_percent_spent' => $overallPercent.'%',
            'is_overspent' => $totalActual > $totalPlanned,
            'categories' => $categories,
        ];

        $text = "Budget Status (Period: {$budget->period_start?->toDateString()} to {$budget->period_end?->toDateString()}):\n"
            .'Total Planned: Rp '.number_format($totalPlanned, 0, ',', '.')."\n"
            .'Total Actual: Rp '.number_format($totalActual, 0, ',', '.')." ({$overallPercent}% spent)\n"
            .'Remaining: Rp '.number_format($totalRemaining, 0, ',', '.')."\n\n"
            ."Category Breakdown:\n".json_encode($categories, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return [
            'content' => [
                [
                    'type' => 'text',
                    'text' => $text,
                ],
            ],
        ];
    }
}
