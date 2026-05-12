<?php

namespace App\DTO;

use Spatie\LaravelData\Data;

class BalanceData extends Data
{
    public function __construct(
        public ?int $id,
        public ?int $user_id,
        public int $initial_amount,
        public int $final_amount,
        public ?UserData $user,
    ) {}
}
