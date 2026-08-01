<?php

namespace App\Queries;

use App\Models\Transaction;
use App\Support\BudgetCycle;

class TransactionQuery extends BaseQuery
{
    protected string $model = Transaction::class;

    protected array $allowedWith = ['balance', 'budget', 'category'];

    protected array $allowedFilters = ['user', 'balance', 'budget', 'type', 'category', 'dateFrom', 'dateTo', 'month', 'year'];

    protected array $searchable = ['description', 'category.name'];

    protected array $sortable = [
        'transactions.date',
        'transactions.created_at',
    ];

    protected array $defaultSorts = [
        '-transactions.date',
        '-transactions.created_at',
    ];

    private bool $budgetJoined = false;

    private function ensureBudgetJoin(): void
    {
        if ($this->budgetJoined) {
            return;
        }

        $this->query->leftJoin(
            'budgets',
            'budgets.id',
            '=',
            'transactions.budget_id',
        );

        $this->budgetJoined = true;
    }

    private function getCycleDateSql(): string
    {
        return BudgetCycle::cycleDateSql('transactions.date', 'budgets.cutoff_day');
    }

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

    public function month(mixed $value): static
    {
        $this->ensureBudgetJoin();

        $cycleDateSql = $this->getCycleDateSql();

        $this->query->whereRaw(
            "CAST(EXTRACT(MONTH FROM ({$cycleDateSql})) AS INTEGER) = ?",
            [(int) $value],
        );

        return $this;
    }

    public function year(mixed $value): static
    {
        $this->ensureBudgetJoin();

        $cycleDateSql = $this->getCycleDateSql();

        $this->query->whereRaw(
            "CAST(EXTRACT(YEAR FROM ({$cycleDateSql})) AS INTEGER) = ?",
            [(int) $value],
        );

        return $this;
    }
}
