<?php

namespace App\DTO;

use App\Enums\CategoryType;
use Carbon\CarbonImmutable;
use Spatie\LaravelData\Data;

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
        public CarbonImmutable $start_date,
        public ?CarbonImmutable $end_date,
        public CarbonImmutable $next_run_date,
        public bool $is_active = true,
    ) {}
}
