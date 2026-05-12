<?php

namespace App\Queries;

use App\Models\Budget;

class BudgetQuery extends BaseQuery
{
    protected string $model = Budget::class;

    protected array $sortable = ['period_start', 'period_end'];

    protected string $defaultSortColumn = 'period_start';

    protected string $defaultSortOrder = 'desc';
}
