<?php

namespace App\Models;

use App\Enums\CategoryType;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $budget_id
 * @property int $category_id
 * @property CategoryType $type
 * @property int $planned_amount
 * @property int $actual_amount
 * @property int $diff_amount
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Budget $budget
 * @property-read Category $category
 * @property-read Collection<int, Transaction> $transactions
 * @property-read int|null $transactions_count
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BudgetItem newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BudgetItem newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BudgetItem query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BudgetItem whereActualAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BudgetItem whereBudgetId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BudgetItem whereCategoryId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BudgetItem whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BudgetItem whereDiffAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BudgetItem whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BudgetItem wherePlannedAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BudgetItem whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BudgetItem whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
#[Fillable(['category_id', 'type', 'planned_amount'])]
class BudgetItem extends Model
{
    protected function casts(): array
    {
        return [
            'type' => CategoryType::class,
            'planned_amount' => 'integer',
            'actual_amount' => 'integer',
            'diff_amount' => 'integer',
        ];
    }

    public function budget(): BelongsTo
    {
        return $this->belongsTo(Budget::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }
}
