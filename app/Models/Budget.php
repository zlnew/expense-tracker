<?php

namespace App\Models;

use App\Enums\CategoryType;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $user_id
 * @property CarbonImmutable $period_start
 * @property CarbonImmutable $period_end
 * @property bool $is_active
 * @property string|null $notes
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property int $cutoff_day
 * @property-read Collection<int, BudgetItem> $expenses
 * @property-read int|null $expenses_count
 * @property-read Collection<int, BudgetItem> $incomes
 * @property-read int|null $incomes_count
 * @property-read Collection<int, BudgetItem> $items
 * @property-read int|null $items_count
 * @property-read Collection<int, Transaction> $transactions
 * @property-read int|null $transactions_count
 * @property-read User $user
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Budget newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Budget newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Budget query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Budget whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Budget whereCutoffDay($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Budget whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Budget whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Budget whereNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Budget wherePeriodEnd($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Budget wherePeriodStart($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Budget whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Budget whereUserId($value)
 *
 * @mixin \Eloquent
 */
#[Fillable(['period_start', 'period_end', 'cutoff_day', 'notes', 'carry_over'])]
class Budget extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'is_active' => 'boolean',
            'carry_over' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(BudgetItem::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(BudgetItem::class)->where('type', CategoryType::EXPENSE);
    }

    public function incomes(): HasMany
    {
        return $this->hasMany(BudgetItem::class)->where('type', CategoryType::INCOME);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }
}
