<?php

namespace App\Mcp\Tools;

use App\Models\Transaction;
use App\Models\User;

class ListTransactionsTool implements ToolInterface
{
    public function name(): string
    {
        return 'list_transactions';
    }

    public function description(): string
    {
        return 'List transactions for the authenticated user with optional filters (date range, category, balance, type, search).';
    }

    public function schema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'limit' => [
                    'type' => 'integer',
                    'description' => 'Maximum number of transactions to return (default: 20, max: 100)',
                ],
                'date_from' => [
                    'type' => 'string',
                    'description' => 'Start date (YYYY-MM-DD)',
                ],
                'date_to' => [
                    'type' => 'string',
                    'description' => 'End date (YYYY-MM-DD)',
                ],
                'type' => [
                    'type' => 'string',
                    'enum' => ['expense', 'income'],
                    'description' => 'Filter by transaction type',
                ],
                'category_id' => [
                    'type' => 'integer',
                    'description' => 'Filter by category ID',
                ],
                'balance_id' => [
                    'type' => 'integer',
                    'description' => 'Filter by balance / account ID',
                ],
                'search' => [
                    'type' => 'string',
                    'description' => 'Search term matching description',
                ],
            ],
        ];
    }

    public function execute(User $user, array $arguments): array
    {
        $limit = min(100, max(1, (int) ($arguments['limit'] ?? 20)));

        $query = Transaction::query()
            ->where('user_id', $user->id)
            ->with(['balance', 'category'])
            ->orderBy('date', 'desc')
            ->orderBy('id', 'desc');

        if (! empty($arguments['date_from'])) {
            $query->whereDate('date', '>=', $arguments['date_from']);
        }
        if (! empty($arguments['date_to'])) {
            $query->whereDate('date', '<=', $arguments['date_to']);
        }
        if (! empty($arguments['type'])) {
            $query->where('type', $arguments['type']);
        }
        if (! empty($arguments['category_id'])) {
            $query->where('category_id', $arguments['category_id']);
        }
        if (! empty($arguments['balance_id'])) {
            $query->where('balance_id', $arguments['balance_id']);
        }
        if (! empty($arguments['search'])) {
            $query->where('description', 'like', '%'.$arguments['search'].'%');
        }

        $transactions = $query->limit($limit)->get();

        $rows = $transactions->map(function (Transaction $t) {
            return [
                'id' => $t->id,
                'date' => $t->date?->toDateString(),
                'type' => $t->type instanceof \BackedEnum ? $t->type->value : (string) $t->type,
                'amount' => $t->amount,
                'amount_formatted' => 'Rp '.number_format($t->amount, 0, ',', '.'),
                'description' => $t->description,
                'category' => $t->category?->name,
                'category_id' => $t->category_id,
                'balance' => $t->balance?->name,
                'balance_id' => $t->balance_id,
                'transfer_group_id' => $t->transfer_group_id,
            ];
        })->all();

        $text = 'Found '.count($rows)." transactions:\n".json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

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
