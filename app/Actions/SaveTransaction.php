<?php

namespace App\Actions;

use App\DTO\TransactionData;
use App\Models\Transaction;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SaveTransaction extends Action
{
    public function __construct(
        private readonly Transaction $transaction,
        private readonly TransactionData $data,
    ) {}

    public function handle(): Transaction
    {
        DB::transaction(function () {
            $userId = Auth::id();
            $oldBalanceId = $this->transaction->balance_id;

            $this->transaction->fill([
                'type' => $this->data->type,
                'date' => $this->data->date,
                'amount' => $this->data->amount,
                'description' => $this->data->description,
                'transfer_group_id' => $this->data->transfer_group_id,
            ]);

            if (! $this->transaction->user_id) {
                $this->transaction->user()->associate($userId);
            }

            if ($this->data->balance_id) {
                $this->transaction->balance()->associate($this->data->balance_id);
            }

            if ($this->data->budget_id) {
                $this->transaction->budget()->associate($this->data->budget_id);
            }

            if ($this->data->budget_item_id) {
                $this->transaction->budgetItem()->associate($this->data->budget_item_id);
            }

            if ($this->data->category_id) {
                $this->transaction->category()->associate($this->data->category_id);
            }

            $this->transaction->save();

            SyncBalance::run($this->transaction->balance_id);

            if ($oldBalanceId && $oldBalanceId !== $this->transaction->balance_id) {
                SyncBalance::run($oldBalanceId);
            }
        });

        return $this->transaction;
    }
}
