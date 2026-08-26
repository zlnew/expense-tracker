<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $fund_id
 * @property int $user_id
 * @property string $type
 * @property int $amount
 * @property CarbonImmutable $date
 * @property int|null $transaction_id
 * @property string|null $group_id
 * @property string|null $description
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read SinkingFund $fund
 * @property-read User $user
 * @property-read Transaction|null $transaction
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FundContribution newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FundContribution newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FundContribution query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FundContribution whereAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FundContribution whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FundContribution whereDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FundContribution whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FundContribution whereFundId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FundContribution whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FundContribution whereTransactionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FundContribution whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FundContribution whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FundContribution whereUserId($value)
 *
 * @mixin \Eloquent
 */
#[Fillable(['fund_id', 'user_id', 'type', 'amount', 'date', 'transaction_id', 'group_id', 'description'])]
class FundContribution extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'date' => 'date',
        ];
    }

    public function fund(): BelongsTo
    {
        return $this->belongsTo(SinkingFund::class, 'fund_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }
}
