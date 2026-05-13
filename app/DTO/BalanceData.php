<?php

namespace App\DTO;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;

class BalanceData extends Data
{
    public function __construct(
        public ?int $id,
        public ?int $user_id,
        public string $name,
        public ?string $description,
        public int $initial_amount = 0,
        public int $final_amount = 0,
        public bool $is_primary = false,
        public ?UserData $user = null,
        #[DataCollectionOf(TransactionData::class)]
        public ?DataCollection $transactions = null,
    ) {}
}
