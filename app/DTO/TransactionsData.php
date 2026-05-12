<?php

namespace App\DTO;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;

class TransactionsData extends Data
{
    public function __construct(
        #[DataCollectionOf(TransactionData::class)]
        public ?DataCollection $items,
    ) {}
}
