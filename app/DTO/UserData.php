<?php

namespace App\DTO;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;

class UserData extends Data
{
    public function __construct(
        public ?int $id,
        public string $name,
        public string $email,
        public ?string $email_verified_at,
        public ?string $two_factor_confirmed_at,
        public ?BudgetData $active_budget,
        #[DataCollectionOf(BudgetData::class)]
        public ?DataCollection $budgets,
        #[DataCollectionOf(BalanceData::class)]
        public ?DataCollection $balances,
        #[DataCollectionOf(TransactionData::class)]
        public ?DataCollection $transactions,
    ) {}
}
