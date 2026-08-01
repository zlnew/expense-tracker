<?php

namespace App\Actions;

use App\DTO\RecurringTransactionData;
use App\Models\RecurringTransaction;
use Illuminate\Support\Facades\Auth;

class SaveRecurringTransaction extends Action
{
    public function __construct(
        private readonly RecurringTransaction $recurringTransaction,
        private readonly RecurringTransactionData $data,
    ) {}

    public function handle(): RecurringTransaction
    {
        $this->recurringTransaction->fill([
            'type' => $this->data->type,
            'balance_id' => $this->data->balance_id,
            'amount' => $this->data->amount,
            'description' => $this->data->description,
            'frequency' => $this->data->frequency,
            'start_date' => $this->data->start_date,
            'end_date' => $this->data->end_date,
            'next_run_date' => $this->data->next_run_date,
            'is_active' => $this->data->is_active,
        ]);

        if ($this->data->category_id) {
            $this->recurringTransaction->category()->associate($this->data->category_id);
        } else {
            $this->recurringTransaction->category()->dissociate();
        }

        if (! $this->recurringTransaction->user_id) {
            $this->recurringTransaction->user()->associate(Auth::id());
        }

        $this->recurringTransaction->save();

        return $this->recurringTransaction;
    }
}
