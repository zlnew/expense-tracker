<?php

namespace App\Queries;

use App\Models\Transaction;

class TransactionQuery extends BaseQuery
{
    protected string $model = Transaction::class;

    protected array $allowedWith = ['budget', 'category'];

    protected array $allowedFilters = ['user', 'balance', 'budget', 'type', 'category', 'dateFrom', 'dateTo'];

    protected array $searchable = ['category.name'];

    protected array $sortable = ['date'];

    protected string $defaultSortColumn = 'date';

    protected string $defaultSortOrder = 'desc';

    public function user(mixed $value): static
    {
        $this->query->where('user_id', $value);

        return $this;
    }

    public function balance(mixed $value): static
    {
        $this->query->where('balance_id', $value);

        return $this;
    }

    public function budget(mixed $value): static
    {
        $this->query->where('budget_id', $value);

        return $this;
    }

    public function type(mixed $value): static
    {
        $this->query->where('type', $value);

        return $this;
    }

    public function category(mixed $value): static
    {
        $this->query->where('category_id', $value);

        return $this;
    }

    public function dateFrom(mixed $value): static
    {
        if (! $this->isValidDate($value)) {
            return $this;
        }

        $this->query->whereDate('date', '>=', $value);

        return $this;
    }

    public function dateTo(mixed $value): static
    {
        if (! $this->isValidDate($value)) {
            return $this;
        }

        $this->query->whereDate('date', '<=', $value);

        return $this;
    }
}
