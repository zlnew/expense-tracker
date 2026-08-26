<?php

namespace App\DTO;

use Carbon\CarbonImmutable;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Attributes\WithTransformer;
use Spatie\LaravelData\Casts\DateTimeInterfaceCast;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Transformers\DateTimeInterfaceTransformer;

class FundData extends Data
{
    public function __construct(
        public ?int $id,
        public ?int $user_id,
        public string $name,
        public int $target_amount,
        public string $cadence,
        public ?int $contribution_amount,
        public ?int $category_id,
        public ?int $from_balance_id,
        #[WithCast(DateTimeInterfaceCast::class, format: 'Y-m-d')]
        #[WithTransformer(DateTimeInterfaceTransformer::class, format: 'Y-m-d')]
        public ?CarbonImmutable $next_due,
        public int $due_interval_months,
        public ?string $notes,
        public ?int $accumulated = null,
        public ?int $percent = null,
        public ?string $status = null,
        public ?int $auto_contribution = null,
        #[WithCast(DateTimeInterfaceCast::class, format: 'Y-m-d')]
        #[WithTransformer(DateTimeInterfaceTransformer::class, format: 'Y-m-d')]
        public ?CarbonImmutable $last_contribution_date = null,
        public ?CategoryData $category = null,
    ) {}
}
