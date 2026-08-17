<?php

namespace App\DTO;

use App\Enums\CategoryType;
use Carbon\CarbonImmutable;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Attributes\WithTransformer;
use Spatie\LaravelData\Casts\DateTimeInterfaceCast;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Transformers\DateTimeInterfaceTransformer;

class RecurringTransactionData extends Data
{
    public function __construct(
        public ?int $id,
        public CategoryType $type,
        public int $balance_id,
        public ?int $category_id,
        public int $amount,
        public ?string $description,
        public string $frequency,
        #[WithCast(DateTimeInterfaceCast::class, format: 'Y-m-d')]
        #[WithTransformer(DateTimeInterfaceTransformer::class, format: 'Y-m-d')]
        public CarbonImmutable $start_date,
        #[WithCast(DateTimeInterfaceCast::class, format: 'Y-m-d')]
        #[WithTransformer(DateTimeInterfaceTransformer::class, format: 'Y-m-d')]
        public ?CarbonImmutable $end_date,
        #[WithCast(DateTimeInterfaceCast::class, format: 'Y-m-d')]
        #[WithTransformer(DateTimeInterfaceTransformer::class, format: 'Y-m-d')]
        public CarbonImmutable $next_run_date,
        public bool $is_active = true,
        public ?BalanceData $balance = null,
        public ?CategoryData $category = null,
    ) {}
}
