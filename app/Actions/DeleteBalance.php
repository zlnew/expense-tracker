<?php

namespace App\Actions;

use App\Models\Balance;
use Illuminate\Validation\ValidationException;

class DeleteBalance extends Action
{
    public function __construct(
        private readonly Balance $balance,
    ) {}

    public function handle(): void
    {
        if ($this->balance->is_primary) {
            throw ValidationException::withMessages([
                'balance' => 'Cannon delete primary balance.',
            ]);
        }

        $hasTransactions = $this->balance->transactions()->exists();

        if ($hasTransactions) {
            throw ValidationException::withMessages([
                'balance' => 'Unable to delete balance because related transactions exist.',
            ]);
        }

        $this->balance->delete();
    }
}
