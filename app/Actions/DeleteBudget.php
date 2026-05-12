<?php

namespace App\Actions;

use App\Models\Budget;
use Illuminate\Validation\ValidationException;

class DeleteBudget extends Action
{
    public function __construct(
        private readonly Budget $budget,
    ) {}

    public function handle(): void
    {
        if ($this->budget->is_active) {
            throw ValidationException::withMessages([
                'budget' => 'Cannot delete active budget.',
            ]);
        }

        $hasItems = $this->budget->items()->exists();
        $hasTransactions = $this->budget->transactions()->exists();

        if ($hasItems || $hasTransactions) {
            throw ValidationException::withMessages([
                'budget' => 'Unable to delete budget because related items or transactions exist.',
            ]);
        }

        $this->budget->delete();
    }
}
