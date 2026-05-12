<?php

namespace App\Queries;

use App\Models\Category;

class CategoryQuery extends BaseQuery
{
    protected string $model = Category::class;

    protected array $allowedFilters = ['type'];

    protected array $searchable = ['name'];

    protected array $sortable = ['name'];

    protected string $defaultSortColumn = 'name';

    protected string $defaultSortOrder = 'asc';

    public function type(mixed $value): static
    {
        $this->query->where('type', $value);

        return $this;
    }
}
