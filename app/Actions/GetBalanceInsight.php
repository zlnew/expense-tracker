<?php

namespace App\Actions;

use App\Models\Balance;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * US-1 Real Balance — single source of truth for the derived balance legs.
 *
 * Nothing here writes: Active stays the stored final_amount, and
 * Reserved/Real are computed on demand so they can never drift from the
 * ledger:
 * - reserved = Σ(contribution − withdrawal) of today-or-earlier ledger rows
 *   across non-deleted funds sourced from this balance;
 * - real = active − reserved.
 *
 * @return array{active: int, reserved: int, real: int}
 */
class GetBalanceInsight extends Action
{
    public function __construct(
        private readonly Balance|int|null $balance = null,
    ) {}

    public function handle(): array
    {
        if ($this->balance === null) {
            throw new \InvalidArgumentException('GetBalanceInsight requires a Balance model or id.');
        }

        $balance = $this->balance instanceof Balance
            ? $this->balance
            : Balance::query()->findOrFail($this->balance);

        $reserved = self::reservedForBalanceId($balance->id);

        return [
            'active' => (int) $balance->final_amount,
            'reserved' => $reserved,
            'real' => ((int) $balance->final_amount) - $reserved,
        ];
    }

    /**
     * Net worth = Σ real across every balance the user owns (US-1 §7.2).
     */
    public static function netWorth(User|int $user): int
    {
        $userId = $user instanceof User ? $user->id : $user;

        $rows = self::reservedByBalanceId(
            Balance::query()->where('user_id', $userId)->pluck('id')->all()
        );

        $totalActive = (int) Balance::query()->where('user_id', $userId)->sum('final_amount');

        return $totalActive - array_sum($rows);
    }

    /**
     * Reserved total for one balance id. Same accumulated definition as
     * GetFundProgress (contribution − withdrawal, date-scoped) folded into a
     * single grouped query; soft-deleted funds are excluded because their
     * reserves no longer exist.
     */
    public static function reservedForBalanceId(int $balanceId): int
    {
        return self::reservedByBalanceId([$balanceId])[$balanceId] ?? 0;
    }

    /**
     * @param  array<int, int>  $balanceIds
     * @return array<int, int> balance_id => reserved
     */
    public static function reservedByBalanceId(array $balanceIds): array
    {
        if ($balanceIds === []) {
            return [];
        }

        $day = now()->startOfDay()->toDateString();

        $rows = DB::table('fund_contributions')
            ->join('sinking_funds', 'sinking_funds.id', '=', 'fund_contributions.fund_id')
            ->whereIn('sinking_funds.from_balance_id', $balanceIds)
            ->whereNull('sinking_funds.deleted_at')
            ->whereDate('fund_contributions.date', '<=', $day)
            ->groupBy('sinking_funds.from_balance_id')
            ->selectRaw('sinking_funds.from_balance_id as balance_id')
            ->selectRaw("COALESCE(SUM(CASE WHEN fund_contributions.type = 'contribution' THEN fund_contributions.amount ELSE 0 END), 0) - COALESCE(SUM(CASE WHEN fund_contributions.type = 'withdrawal' THEN fund_contributions.amount ELSE 0 END), 0) as reserved")
            ->pluck('reserved', 'balance_id')
            ->all();

        $out = array_fill_keys($balanceIds, 0);
        foreach ($rows as $bid => $reserved) {
            $out[(int) $bid] = (int) $reserved;
        }

        return $out;
    }
}
