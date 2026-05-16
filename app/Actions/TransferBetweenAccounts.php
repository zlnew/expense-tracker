<?php

namespace App\Actions;

use App\DTO\TransactionData;
use App\DTO\TransferBetweenAccountsData;
use App\Enums\CategoryType;
use App\Models\Balance;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TransferBetweenAccounts extends Action
{
    public function __construct(
        private readonly ?TransferBetweenAccountsData $data,
    ) {}

    public function handle(): void
    {
        $sourceAccount = Balance::query()->find($this->data->from_account_id);
        if (! $sourceAccount) {
            throw ValidationException::withMessages([
                'account' => 'Invalid account source',
            ]);
        }

        $destinationAccount = Balance::query()->find($this->data->to_account_id);
        if (! $destinationAccount) {
            throw ValidationException::withMessages([
                'account' => 'Invalid account destination',
            ]);
        }

        if ($sourceAccount->final_amount < 0) {
            throw ValidationException::withMessages([
                'account' => 'Insufficient balance',
            ]);
        }

        DB::transaction(function () use ($destinationAccount, $sourceAccount) {
            SaveTransaction::run(new Transaction, TransactionData::from([
                'balance_id' => $sourceAccount->id,
                'type' => CategoryType::EXPENSE->value,
                'date' => $this->data->date,
                'amount' => $this->data->amount,
                'description' => $this->data->description,
            ]));

            SaveTransaction::run(new Transaction, TransactionData::from([
                'balance_id' => $destinationAccount->id,
                'type' => CategoryType::INCOME->value,
                'date' => $this->data->date,
                'amount' => $this->data->amount,
                'description' => $this->data->description,
            ]));
        });
    }
}
