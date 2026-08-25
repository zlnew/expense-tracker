<?php

namespace App\DTO;

use Carbon\CarbonImmutable;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Attributes\WithTransformer;
use Spatie\LaravelData\Casts\DateTimeInterfaceCast;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Transformers\DateTimeInterfaceTransformer;

class FundContributionData extends Data
{
    public function __construct(
        public ?int $id = null,
        public ?int $fund_id = null,
        public ?int $user_id = null,
        public string $type = 'contribution',
        public int $amount = 0,
        #[WithCast(DateTimeInterfaceCast::class, format: 'Y-m-d')]
        #[WithTransformer(DateTimeInterfaceTransformer::class, format: 'Y-m-d')]
        public ?CarbonImmutable $date = null,
        public ?int $transaction_id = null,
        public ?string $group_id = null,
        public ?string $description = null,
        public ?int $balance_id = null,
        public ?TransactionData $transaction = null,
    ) {}
}
