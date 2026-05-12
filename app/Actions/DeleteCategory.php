<?php

namespace App\Actions;

use App\Models\Category;
use Illuminate\Validation\ValidationException;

class DeleteCategory extends Action
{
    public function __construct(
        private readonly Category $category,
    ) {}

    public function handle(): void
    {
        $hasBudgetItems = $this->category->budgetItems()->exists();
        $hasTransactions = $this->category->transactions()->exists();

        if ($hasBudgetItems || $hasTransactions) {
            throw ValidationException::withMessages([
                'category' => 'Unable to delete category because related budget items or transactions exist.',
            ]);
        }

        $this->category->delete();
    }
}
