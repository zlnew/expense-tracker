<?php

namespace App\Models;

use App\Actions\GetBalanceInsight;
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
 * @property string|null $description
 * @property int $initial_amount
 * @property int $final_amount
 * @property bool $is_primary
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Collection<int, Transaction> $transactions
 * @property-read int|null $transactions_count
 * @property-read User $user
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Balance newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Balance newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Balance query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Balance whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Balance whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Balance whereFinalAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Balance whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Balance whereInitialAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Balance whereIsPrimary($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Balance whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Balance whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Balance whereUserId($value)
 *
 * @mixin \Eloquent
 */
#[Fillable(['name', 'description', 'initial_amount', 'final_amount', 'is_primary'])]
class Balance extends Model
{
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'initial_amount' => 'integer',
            'final_amount' => 'integer',
            'is_primary' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * Sinking funds that reserve against this balance.
     */
    public function sinkingFunds(): HasMany
    {
        return $this->hasMany(SinkingFund::class, 'from_balance_id');
    }

    /**
     * Reserved = Σ reserves of funds whose from_balance_id is this balance.
     * Real = final_amount − reserved. Both derived — never stored.
     */
    public function getReservedAttribute(): int
    {
        return (int) GetBalanceInsight::run($this)['reserved'];
    }

    public function getRealAttribute(): int
    {
        return (int) GetBalanceInsight::run($this)['real'];
    }
}
