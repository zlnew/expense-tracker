<?php

namespace App\Queries;

use App\Models\Budget;

class BudgetQuery extends BaseQuery
{
    protected string $model = Budget::class;

    protected array $sortable = ['period_start', 'period_end', 'is_active'];

    protected array $defaultSorts = ['-is_active', '-period_start'];
}
