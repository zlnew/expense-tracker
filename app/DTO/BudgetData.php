<?php

namespace App\DTO;

use App\Enums\CategoryType;
use Spatie\LaravelData\Data;

class BudgetData extends Data
{
    public function __construct(
        public ?int $id,
        public ?int $user_id,
        public int $category_id,
        public CategoryType $type,
        public int $planned_amount,
        public int $actual_amount,
        public int $diff_amount,
        public ?UserData $user,
        public ?CategoryData $category,
    ) {}
}
