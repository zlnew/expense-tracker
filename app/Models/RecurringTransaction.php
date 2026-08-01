<?php

namespace App\Models;

use App\Enums\CategoryType;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property CategoryType $type
 * @property int $balance_id
 * @property int|null $category_id
 * @property int $amount
 * @property string|null $description
 * @property string $frequency
 * @property CarbonImmutable $start_date
 * @property CarbonImmutable|null $end_date
 * @property CarbonImmutable $next_run_date
 * @property bool $is_active
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RecurringTransaction newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RecurringTransaction newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RecurringTransaction query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RecurringTransaction whereAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RecurringTransaction whereBalanceId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RecurringTransaction whereCategoryId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RecurringTransaction whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RecurringTransaction whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RecurringTransaction whereEndDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RecurringTransaction whereFrequency($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RecurringTransaction whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RecurringTransaction whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RecurringTransaction whereNextRunDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RecurringTransaction whereStartDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RecurringTransaction whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RecurringTransaction whereUserId($value)
 *
 * @mixin \Eloquent
 */
#[Fillable(['type', 'balance_id', 'category_id', 'amount', 'description', 'frequency', 'start_date', 'end_date', 'next_run_date', 'is_active'])]
class RecurringTransaction extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'type' => CategoryType::class,
            'start_date' => 'immutable_date',
            'end_date' => 'immutable_date',
            'next_run_date' => 'immutable_date',
            'is_active' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function balance(): BelongsTo
    {
        return $this->belongsTo(Balance::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
