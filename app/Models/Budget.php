<?php

namespace App\Models;

use App\Enums\CategoryType;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property int $category_id
 * @property CategoryType $type
 * @property int $planned_amount
 * @property int $actual_amount
 * @property int $diff_amount
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Category $category
 * @property-read User $user
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Budget newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Budget newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Budget query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Budget whereActualAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Budget whereCategoryId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Budget whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Budget whereDiffAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Budget whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Budget wherePlannedAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Budget whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Budget whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Budget whereUserId($value)
 *
 * @mixin \Eloquent
 */
#[Fillable(['type', 'planned_amount', 'actual_amount', 'diff_amount'])]
class Budget extends Model
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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
