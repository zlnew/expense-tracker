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
        public ?BudgetData $expense_budget,
        public ?BudgetData $income_budget,
        #[DataCollectionOf(TransactionData::class)]
        public ?DataCollection $transactions,
    ) {}
}
