<?php

namespace App\Actions;

use App\Models\Budget;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeleteBudget extends Action
{
    public function __construct(
        private readonly Budget $budget,
    ) {}

    public function handle(): void
    {
        DB::transaction(function () {
            if ($this->budget->is_active) {
                throw ValidationException::withMessages([
                    'budget' => 'Cannot delete active budget.',
                ]);
            }

            $hasTransactions = $this->budget->transactions()->exists();

            if ($hasTransactions) {
                throw ValidationException::withMessages([
                    'budget' => 'Unable to delete budget because related transactions exist.',
                ]);
            }

            $this->budget->items()->delete();

            $this->budget->delete();
        });
    }
}
