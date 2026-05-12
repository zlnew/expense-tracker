<?php

namespace App\DTO;

use App\Enums\CategoryType;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;

class CategoryData extends Data
{
    public function __construct(
        public ?int $id,
        public CategoryType $type,
        public string $name,
        #[DataCollectionOf(BudgetItemData::class)]
        public ?DataCollection $budget_items,
        #[DataCollectionOf(TransactionData::class)]
        public ?DataCollection $transactions,
    ) {}
}
