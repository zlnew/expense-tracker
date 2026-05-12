<?php

namespace App\DTO;

use App\Enums\CategoryType;
use Carbon\CarbonImmutable;
use Spatie\LaravelData\Data;

class TransactionData extends Data
{
    public function __construct(
        public ?int $id,
        public ?int $user_id,
        public int $category_id,
        public CategoryType $type,
        public CarbonImmutable $date,
        public int $amount,
        public ?string $description,
        public ?UserData $user,
        public ?CategoryData $category,
    ) {}
}
