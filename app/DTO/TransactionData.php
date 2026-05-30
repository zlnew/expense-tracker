<?php

namespace App\DTO;

use App\Enums\CategoryType;
use Carbon\CarbonImmutable;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Attributes\WithTransformer;
use Spatie\LaravelData\Casts\DateTimeInterfaceCast;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Transformers\DateTimeInterfaceTransformer;

class TransactionData extends Data
{
    public function __construct(
        public ?int $id,
        public ?int $user_id,
        public int $balance_id,
        public ?int $budget_id,
        public ?int $budget_item_id,
        public ?int $category_id,
        public CategoryType $type,
        #[WithCast(DateTimeInterfaceCast::class, format: 'Y-m-d')]
        #[WithTransformer(DateTimeInterfaceTransformer::class, format: 'Y-m-d')]
        public CarbonImmutable $date,
        public int $amount,
        public ?string $description,
        public ?int $cycle_month,
        public ?int $cycle_year,
        public ?UserData $user,
        public ?BalanceData $balance,
        public ?BudgetData $budget,
        public ?BudgetItemData $budget_item,
        public ?CategoryData $category,
    ) {}
}
