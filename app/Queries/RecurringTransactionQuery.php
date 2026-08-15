<?php

namespace App\Queries;

use App\Models\RecurringTransaction;

class RecurringTransactionQuery extends BaseQuery
{
    protected string $model = RecurringTransaction::class;

    protected array $allowedWith = ['balance', 'category'];

    protected array $searchable = ['description', 'category.name'];

    protected array $sortable = [
        'is_active',
        'next_run_date',
        'created_at',
        'amount',
        'frequency',
    ];

    protected array $defaultSorts = [
        '-is_active',
        'next_run_date',
    ];
}
