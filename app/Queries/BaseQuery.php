<?php

namespace App\Queries;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

abstract class BaseQuery
{
    protected string $model;

    protected Builder $query;

    protected bool $initialized = false;

    protected array $with = [];

    protected array $allowedWith = [];

    protected array $filters = [];

    protected array $allowedFilters = [];

    protected array $searchable = [];

    protected array $sortable = ['created_at'];

    protected string $defaultSortColumn = 'created_at';

    protected string $defaultSortOrder = 'desc';

    protected int $defaultLimit = 10;

    protected int $defaultPerPage = 10;

    public static function with(array $with = []): static
    {
        $instance = new static;
        $instance->with = $with;

        return $instance;
    }

    public function apply(array $filters = []): static
    {
        $this->filters = $filters;

        return $this;
    }

    public static function make(array $filters = [], array $with = []): static
    {
        $instance = new static;
        $instance->filters = $filters;
        $instance->with = $with;

        return $instance;
    }

    protected function boot(): void
    {
        if ($this->initialized) {
            return;
        }

        $model = new $this->model;

        $this->query = $model->newQuery();

        $this->applyWith();
        $this->applyFilters();

        $this->initialized = true;
    }

    protected function applyFilters(): void
    {
        foreach ($this->filters as $key => $value) {
            $method = Str::camel($key);

            if (
                in_array($method, $this->allowedFilters) &&
                method_exists($this, $method) &&
                ! $this->shouldSkip($value)
            ) {
                $this->$method($value);
            }
        }

        $this->applySearch();
        $this->applySort();
    }

    protected function applyWith(): void
    {
        $with = $this->with;

        if (! empty($this->filters['with'])) {
            $requested = explode(',', $this->filters['with']);

            foreach ($requested as $relation) {
                $relation = trim($relation);

                if ($this->isAllowedRelation($relation)) {
                    $with[] = $relation;
                }
            }
        }

        if (! empty($with)) {
            $this->query->with(array_unique($with));
        }
    }

    protected function applySearch(): void
    {
        if (empty($this->filters['search']) || empty($this->searchable)) {
            return;
        }

        $search = $this->filters['search'];

        $this->query->where(function ($q) use ($search) {
            foreach ($this->searchable as $column) {
                if (str_contains($column, '.')) {
                    $this->applyRelationSearch($q, $column, $search);
                } else {
                    $q->orWhere($column, 'ilike', "%{$search}%");
                }
            }
        });
    }

    protected function applyRelationSearch(Builder $query, string $column, string $search): void
    {
        $segments = explode('.', $column);

        $field = array_pop($segments);
        $relation = implode('.', $segments);

        $query->orWhereHas($relation, function ($q) use ($field, $search) {
            $q->where($field, 'ilike', "%{$search}%");
        });
    }

    protected function applySort(): void
    {
        $column = $this->filters['sort_column'] ?? $this->defaultSortColumn;
        $order = strtolower($this->filters['sort_order'] ?? $this->defaultSortOrder);

        if (! in_array($column, $this->sortable)) {
            $column = $this->defaultSortColumn;
        }

        $order = $order === 'asc' ? 'asc' : 'desc';

        $this->query->orderBy($column, $order);
    }

    public function get(): Collection
    {
        $this->boot();

        return $this->query
            ->limit($this->filters['limit'] ?? $this->defaultLimit)
            ->get();
    }

    public function paginate(): LengthAwarePaginator
    {
        $this->boot();

        return $this->query
            ->paginate($this->filters['per_page'] ?? $this->defaultPerPage)
            ->appends($this->paginationQueryParams());
    }

    public function result(): Collection|LengthAwarePaginator
    {
        $isPaginate = $this->boolean($this->filters['is_paginate'] ?? false);

        return $isPaginate
            ? $this->paginate()
            : $this->get();
    }

    protected function paginationQueryParams(): array
    {
        return collect($this->filters)
            ->except(['is_paginate'])
            ->filter(fn ($v) => $v !== null && $v !== '')
            ->toArray();
    }

    protected function isAllowedRelation(string $relation): bool
    {
        if (in_array($relation, $this->allowedWith)) {
            return true;
        }

        $segments = explode('.', $relation);
        $current = '';

        foreach ($segments as $segment) {
            $current = $current ? "{$current}.{$segment}" : $segment;

            if (! in_array($current, $this->allowedWith)) {
                return false;
            }
        }

        return true;
    }

    protected function boolean(mixed $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    protected function shouldSkip(mixed $value): bool
    {
        return $value === null || $value === '' || $value === 'all';
    }

    protected function isValidDate(mixed $value): bool
    {
        if (empty($value)) {
            return false;
        }

        return Carbon::hasFormat($value, 'Y-m-d');
    }
}
