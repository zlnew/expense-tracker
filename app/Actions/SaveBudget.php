<?php

namespace App\Actions;

use App\DTO\BudgetData;
use App\Models\Budget;
use Illuminate\Support\Facades\Auth;

class SaveBudget extends Action
{
    public function __construct(
        private readonly Budget $budget,
        private readonly BudgetData $data,
    ) {}

    public function handle(): void
    {
        $this->budget->fill([
            'period_start' => $this->data->period_start,
            'period_end' => $this->data->period_end,
            'notes' => $this->data->notes,
        ]);

        if (! $this->budget->user_id) {
            $this->budget->user()->associate(Auth::user());
        }

        $this->budget->save();
    }
}
