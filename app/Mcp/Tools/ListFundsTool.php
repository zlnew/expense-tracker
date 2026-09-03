<?php

namespace App\Mcp\Tools;

use App\Actions\GetFundProgress;
use App\Models\SinkingFund;
use App\Models\User;

class ListFundsTool implements ToolInterface
{
    public function name(): string
    {
        return 'list_funds';
    }

    public function description(): string
    {
        return 'List all sinking funds with target amounts, accumulated reserves, progress percentage, next due dates, and source accounts.';
    }

    public function schema(): array
    {
        return [
            'type' => 'object',
            'properties' => [],
        ];
    }

    public function execute(User $user, array $arguments): array
    {
        $funds = SinkingFund::query()
            ->where('user_id', $user->id)
            ->with(['sourceBalance', 'category'])
            ->orderBy('next_due')
            ->get();

        $rows = [];
        foreach ($funds as $f) {
            $progress = GetFundProgress::run($f);

            $rows[] = [
                'id' => $f->id,
                'name' => $f->name,
                'category' => $f->category?->name,
                'source_balance' => $f->sourceBalance?->name ?? 'Default',
                'target_amount' => $f->target_amount,
                'target_formatted' => 'Rp '.number_format($f->target_amount, 0, ',', '.'),
                'accumulated' => $progress->accumulated,
                'accumulated_formatted' => 'Rp '.number_format($progress->accumulated, 0, ',', '.'),
                'percent' => $progress->percent.'%',
                'status' => $progress->status,
                'next_due' => $f->next_due?->toDateString(),
                'cadence' => $f->cadence,
                'notes' => $f->notes,
            ];
        }

        $text = 'Sinking Funds ('.count($rows)." funds):\n".json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

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
