<?php

namespace App\Actions;

use App\Models\Budget;
use Illuminate\Support\Facades\DB;

class SetActiveBudget extends Action
{
    public function __construct(
        private readonly Budget $budget,
    ) {}

    public function handle(): void
    {
        DB::transaction(function () {
            $userId = $this->budget->user_id;

            Budget::query()->where('user_id', $userId)->update(['is_active' => false]);

            $this->budget->is_active = true;
            $this->budget->save();
        });
    }
}
