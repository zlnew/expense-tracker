<?php

namespace App\DTO;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;

class BudgetItemsData extends Data
{
    public function __construct(
        #[DataCollectionOf(BudgetItemData::class)]
        public ?DataCollection $items,
    ) {}
}
