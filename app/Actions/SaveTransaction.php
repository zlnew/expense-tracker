<?php

namespace App\Actions;

use App\DTO\TransactionData;
use App\Models\Transaction;
use Illuminate\Support\Facades\Auth;

class SaveTransaction extends Action
{
    public function __construct(
        private readonly Transaction $transaction,
        private readonly TransactionData $data,
    ) {}

    public function handle(): void
    {
        $this->transaction->fill([
            'type' => $this->data->type,
            'date' => $this->data->date,
            'amount' => $this->data->amount,
            'description' => $this->data->description,
        ]);

        if (! $this->transaction->user_id) {
            $this->transaction->user()->associate(Auth::user());
        }

        if (! $this->transaction->budget_id && $this->data->budget_id) {
            $this->transaction->budget()->associate($this->data->budget_id);
        }

        if ($this->data->budget_item_id) {
            $this->transaction->budgetItem()->associate($this->data->budget_item_id);
        }

        if ($this->data->category_id) {
            $this->transaction->category()->associate($this->data->category_id);
        }

        $this->transaction->save();
    }
}
