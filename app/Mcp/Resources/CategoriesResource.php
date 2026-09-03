<?php

namespace App\Mcp\Resources;

use App\Models\Category;
use App\Models\User;

class CategoriesResource implements ResourceInterface
{
    public function uri(): string
    {
        return 'expense-tracker://categories';
    }

    public function name(): string
    {
        return 'Category Registry';
    }

    public function description(): string
    {
        return 'List of all categories (income and expense) available for transaction logging.';
    }

    public function mimeType(): string
    {
        return 'application/json';
    }

    public function read(User $user): string
    {
        $categories = Category::query()
            ->where('user_id', $user->id)
            ->orderBy('type')
            ->orderBy('name')
            ->get(['id', 'name', 'type']);

        return json_encode($categories, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }
}
