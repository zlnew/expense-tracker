<?php

namespace App\Queries;

use App\Models\RecurringTransaction;

class RecurringTransactionQuery extends BaseQuery
{
    protected string $model = RecurringTransaction::class;

    protected array $allowedWith = ['balance', 'category'];

    protected array $searchable = ['description', 'category.name', 'balance.name'];

    protected array $sortable = [
        'description',
        'amount',
        'frequency',
        'is_active',
        'next_run_date',
        'start_date',
        'end_date',
    ];

    protected array $defaultSorts = ['-is_active', 'next_run_date'];
}
