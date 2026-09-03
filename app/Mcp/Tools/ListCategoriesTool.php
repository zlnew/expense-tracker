<?php

namespace App\Mcp\Tools;

use App\Models\Category;
use App\Models\User;

class ListCategoriesTool implements ToolInterface
{
    public function name(): string
    {
        return 'list_categories';
    }

    public function description(): string
    {
        return 'List all income and expense categories available for transaction classification.';
    }

    public function schema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'type' => [
                    'type' => 'string',
                    'enum' => ['expense', 'income'],
                    'description' => 'Optional filter by category type (expense or income)',
                ],
            ],
        ];
    }

    public function execute(User $user, array $arguments): array
    {
        $query = Category::query()
            ->where('user_id', $user->id)
            ->orderBy('type')
            ->orderBy('name');

        if (! empty($arguments['type'])) {
            $query->where('type', $arguments['type']);
        }

        $categories = $query->get(['id', 'name', 'type']);

        $grouped = [
            'expense' => [],
            'income' => [],
        ];

        foreach ($categories as $c) {
            $typeKey = $c->type instanceof \BackedEnum ? $c->type->value : (string) $c->type;
            $grouped[$typeKey][] = [
                'id' => $c->id,
                'name' => $c->name,
            ];
        }

        $text = "Available Categories:\n".json_encode($grouped, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

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
