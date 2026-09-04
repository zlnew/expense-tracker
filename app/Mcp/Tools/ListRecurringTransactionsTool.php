<?php

namespace App\Mcp\Tools;

use App\Models\RecurringTransaction;
use App\Models\User;

class ListRecurringTransactionsTool implements ToolInterface
{
    public function name(): string
    {
        return 'list_recurring_transactions';
    }

    public function description(): string
    {
        return 'List recurring transactions (scheduled bills and automated income) with frequencies and next run dates.';
    }

    public function schema(): array
    {
        return [
            'type' => 'object',
            'properties' => (object) [],
        ];
    }

    public function execute(User $user, array $arguments): array
    {
        $recurrings = RecurringTransaction::query()
            ->where('user_id', $user->id)
            ->with(['balance', 'category'])
            ->orderBy('next_run_date')
            ->get();

        $rows = $recurrings->map(function (RecurringTransaction $r) {
            return [
                'id' => $r->id,
                'type' => $r->type instanceof \BackedEnum ? $r->type->value : (string) $r->type,
                'amount' => $r->amount,
                'amount_formatted' => 'Rp '.number_format($r->amount, 0, ',', '.'),
                'description' => $r->description,
                'frequency' => $r->frequency instanceof \BackedEnum ? $r->frequency->value : (string) $r->frequency,
                'category' => $r->category?->name,
                'balance' => $r->balance?->name,
                'next_run_date' => $r->next_run_date?->toDateString(),
                'is_active' => (bool) $r->is_active,
            ];
        })->all();

        $text = 'Recurring Transactions ('.count($rows)." items):\n".json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

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
