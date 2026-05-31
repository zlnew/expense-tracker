<?php

namespace App\Models;

use App\Enums\CategoryType;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property int $balance_id
 * @property int|null $budget_id
 * @property int|null $budget_item_id
 * @property int|null $category_id
 * @property CategoryType $type
 * @property CarbonImmutable $date
 * @property int $amount
 * @property string|null $description
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Balance $balance
 * @property-read Budget|null $budget
 * @property-read BudgetItem|null $budgetItem
 * @property-read Category|null $category
 * @property-read int $cycle_month
 * @property-read int $cycle_year
 * @property-read User $user
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction whereAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction whereBalanceId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction whereBudgetId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction whereBudgetItemId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction whereCategoryId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction whereDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction whereUserId($value)
 *
 * @mixin \Eloquent
 */
#[Fillable(['type', 'date', 'amount', 'description'])]
class Transaction extends Model
{
    protected function casts(): array
    {
        return [
            'type' => CategoryType::class,
            'date' => 'date',
            'amount' => 'integer',
        ];
    }

    protected function cycleMonth(): Attribute
    {
        return Attribute::make(
            get: fn (): int => $this->getCycleDate()->month,
        );
    }

    protected function cycleYear(): Attribute
    {
        return Attribute::make(
            get: fn (): int => $this->getCycleDate()->year,
        );
    }

    private function getCycleDate(): CarbonImmutable
    {
        /** @var CarbonImmutable $date */
        $date = CarbonImmutable::parse($this->date);
        $cutoffDay = $this->budget?->cutoff_day;

        if ($cutoffDay === null) {
            return $date;
        }

        $effectiveCutoff = $this->resolveEffectiveCutoff($date, $cutoffDay);

        if ($date->gt($effectiveCutoff)) {
            return $date->addMonthNoOverflow();
        }

        return $date;
    }

    private function resolveEffectiveCutoff(CarbonImmutable $date, int $cutoffDay): CarbonImmutable
    {
        $resolvedDay = min($cutoffDay, $date->daysInMonth);

        return $date->setDay($resolvedDay)->startOfDay();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function balance(): BelongsTo
    {
        return $this->belongsTo(Balance::class);
    }

    public function budget(): BelongsTo
    {
        return $this->belongsTo(Budget::class);
    }

    public function budgetItem(): BelongsTo
    {
        return $this->belongsTo(BudgetItem::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
