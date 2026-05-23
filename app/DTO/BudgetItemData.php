<?php

namespace App\DTO;

use App\Enums\CategoryType;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;

class BudgetItemData extends Data
{
    public function __construct(
        public ?int $id,
        public ?int $budget_id,
        public int $category_id,
        public CategoryType $type,
        public int $planned_amount,
        public ?BudgetData $budget = null,
        public ?CategoryData $category = null,
        #[DataCollectionOf(TransactionData::class)]
        public ?DataCollection $transactions = null,
    ) {}
}
