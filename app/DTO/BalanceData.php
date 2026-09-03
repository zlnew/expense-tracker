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
        public ?int $reconciled_amount = null,
        public ?string $reconciled_at = null,
        public ?int $drift = null,
        public bool $is_drift_flagged = false,
        public bool $is_primary = false,
        public int $reserved = 0,
        public int $real = 0,
        public int $real_balance = 0,
        public ?UserData $user = null,
        #[DataCollectionOf(TransactionData::class)]
        public ?DataCollection $transactions = null,
    ) {}
}
