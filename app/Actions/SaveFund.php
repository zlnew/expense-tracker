<?php

namespace App\Actions;

use App\DTO\FundData;
use App\Models\SinkingFund;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SaveFund extends Action
{
    public function __construct(
        private readonly SinkingFund $fund,
        private readonly FundData $data,
    ) {}

    public function handle(): SinkingFund
    {
        return DB::transaction(function () {
            if (! $this->fund->user_id) {
                // Creating a fund is user-scoped server-side.
                $this->fund->user()->associate(Auth::id());
            }

            $this->fund->fill([
                'name' => $this->data->name,
                'target_amount' => $this->data->target_amount,
                'cadence' => $this->data->cadence,
                'contribution_amount' => $this->data->contribution_amount,
                'category_id' => $this->data->category_id,
                'next_due' => $this->data->next_due,
                'due_interval_months' => $this->data->due_interval_months,
                // Re-anchor the cadence whenever next_due is set (create or
                // edit); PayFromFund rolls from this day, clamped per month.
                'anchor_day' => $this->data->next_due?->day,
                'notes' => $this->data->notes,
            ]);

            $this->fund->save();

            return $this->fund;
        });
    }
}
