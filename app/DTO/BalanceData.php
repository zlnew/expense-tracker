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
        public int $initial_amount,
        public int $final_amount,
        public bool $is_primary,
        public ?UserData $user,
        #[DataCollectionOf(TransactionData::class)]
        public ?DataCollection $transactions,
    ) {}
}
