<?php

namespace App\DTO;

use Carbon\CarbonImmutable;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Attributes\WithTransformer;
use Spatie\LaravelData\Casts\DateTimeInterfaceCast;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;
use Spatie\LaravelData\Transformers\DateTimeInterfaceTransformer;

class BudgetData extends Data
{
    public function __construct(
        public ?int $id,
        public ?int $user_id,
        #[WithCast(DateTimeInterfaceCast::class, format: 'Y-m-d')]
        #[WithTransformer(DateTimeInterfaceTransformer::class, format: 'Y-m-d')]
        public CarbonImmutable $period_start,
        #[WithCast(DateTimeInterfaceCast::class, format: 'Y-m-d')]
        #[WithTransformer(DateTimeInterfaceTransformer::class, format: 'Y-m-d')]
        public CarbonImmutable $period_end,
        public bool $is_active = false,
        public ?string $notes = null,
        #[WithTransformer(DateTimeInterfaceTransformer::class, format: 'Y-m-d H:i:s')]
        public ?CarbonImmutable $updated_at = null,
        public ?UserData $user = null,
        #[DataCollectionOf(BudgetItemData::class)]
        public ?DataCollection $items = null,
        #[DataCollectionOf(BudgetItemData::class)]
        public ?DataCollection $expenses = null,
        #[DataCollectionOf(BudgetItemData::class)]
        public ?DataCollection $incomes = null,
    ) {}
}
