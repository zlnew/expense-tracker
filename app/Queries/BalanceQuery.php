<?php

namespace App\Queries;

use App\Models\Balance;

class BalanceQuery extends BaseQuery
{
    protected string $model = Balance::class;

    protected array $searchable = ['name'];

    protected array $sortable = ['name', 'is_primary'];

    protected array $defaultSorts = ['-is_primary', 'name'];
}
