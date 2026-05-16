<?php

namespace App\DTO;

use Carbon\CarbonImmutable;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Casts\DateTimeInterfaceCast;
use Spatie\LaravelData\Data;

class TransferBetweenAccountsData extends Data
{
    public function __construct(
        public int $from_account_id,
        public int $to_account_id,
        #[WithCast(DateTimeInterfaceCast::class, format: 'Y-m-d')]
        public CarbonImmutable $date,
        public int $amount,
        public ?string $description,
    ) {}
}
