<?php

namespace App\DTO;

use Carbon\CarbonImmutable;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;

class BudgetData extends Data
{
    public function __construct(
        public ?int $id,
        public ?int $user_id,
        public CarbonImmutable $period_start,
        public CarbonImmutable $period_end,
        public bool $is_active,
        public ?string $notes,
        public ?UserData $user,
        #[DataCollectionOf(BudgetItemData::class)]
        public ?DataCollection $items,
        #[DataCollectionOf(BudgetItemData::class)]
        public ?DataCollection $expenses,
        #[DataCollectionOf(BudgetItemData::class)]
        public ?DataCollection $incomes,
    ) {}
}
