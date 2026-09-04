<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property int $target_amount
 * @property string $cadence
 * @property int|null $contribution_amount
 * @property int|null $category_id
 * @property CarbonImmutable|null $next_due
 * @property int $due_interval_months
 * @property int|null $anchor_day
 * @property string|null $notes
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property CarbonImmutable|null $deleted_at
 * @property-read User $user
 * @property-read Category|null $category
 * @property-read Collection<int, FundContribution> $contributions
 * @property-read int|null $contributions_count
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SinkingFund newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SinkingFund newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SinkingFund query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SinkingFund whereCadence($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SinkingFund whereCategoryId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SinkingFund whereContributionAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SinkingFund whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SinkingFund whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SinkingFund whereDueIntervalMonths($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SinkingFund whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SinkingFund whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SinkingFund whereNextDue($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SinkingFund whereNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SinkingFund whereTargetAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SinkingFund whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SinkingFund whereUserId($value)
 *
 * @mixin \Eloquent
 */
#[Fillable(['user_id', 'name', 'target_amount', 'cadence', 'contribution_amount', 'category_id', 'from_balance_id', 'next_due', 'due_interval_months', 'anchor_day', 'notes'])]
class SinkingFund extends Model
{
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'target_amount' => 'integer',
            'contribution_amount' => 'integer',
            'due_interval_months' => 'integer',
            'anchor_day' => 'integer',
            'next_due' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function sourceBalance(): BelongsTo
    {
        return $this->belongsTo(Balance::class, 'from_balance_id');
    }

    public function contributions(): HasMany
    {
        return $this->hasMany(FundContribution::class, 'fund_id');
    }
}
