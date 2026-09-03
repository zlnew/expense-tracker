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

            $targetUserId = $this->data->user_id ?? $userId;

            if (! $this->transaction->user_id && $targetUserId) {
                $this->transaction->user()->associate($targetUserId);
            }

            if ($this->data->balance_id) {
                $this->transaction->balance()->associate($this->data->balance_id);
            }

            $resolvedUserId = (int) ($this->transaction->user_id ?? $targetUserId);
            $budgetId = $this->data->budget_id;
            $budgetItemId = $this->data->budget_item_id;

            // Internal balance transfers have no category and should never link to budget envelopes
            $isInternalTransfer = $this->data->transfer_group_id !== null && $this->data->category_id === null;
            $isNew = ! $this->transaction->exists;
            $shouldAutoResolve = ! $isInternalTransfer
                && $this->data->category_id
                && $resolvedUserId
                && ($isNew ? (! $budgetId || ! $budgetItemId) : ($budgetId && ! $budgetItemId));

            if ($shouldAutoResolve) {
                ['budget_id' => $autoBudgetId, 'budget_item_id' => $autoBudgetItemId] = ResolveTransactionBudgetLink::run(
                    $resolvedUserId,
                    $this->data->category_id,
                    $budgetId,
                    $budgetItemId,
                );
                $budgetId = $budgetId ?? $autoBudgetId;
                $budgetItemId = $budgetItemId ?? $autoBudgetItemId;
            }

            if ($budgetId) {
                $this->transaction->budget()->associate($budgetId);
            } else {
                $this->transaction->budget()->dissociate();
            }

            if ($budgetItemId) {
                $this->transaction->budgetItem()->associate($budgetItemId);
            } else {
                $this->transaction->budgetItem()->dissociate();
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
