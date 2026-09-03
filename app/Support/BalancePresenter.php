<?php

namespace App\Support;

use App\Actions\GetBalanceInsight;
use App\DTO\BalanceData;
use App\Models\Balance;
use App\Models\FundContribution;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Spatie\LaravelData\DataCollection;
use Spatie\LaravelData\PaginatedDataCollection;

/**
 * Presents balances with the US-1 derived legs (reserved / real) attached.
 *
 * Real = Active (final_amount) − Reserved (Σ accumulated reserves of funds
 * sourced from this balance). Reserved math comes from GetBalanceInsight
 * (foundation bytes); the batch helper below is only an aggregation over the
 * same per-fund accumulated definition so list surfaces avoid N+1.
 */
class BalancePresenter
{
    public static function fromModel(Balance $balance): BalanceData
    {
        $insight = GetBalanceInsight::run($balance);

        return self::makeData($balance, $insight['reserved'], $insight['real']);
    }

    /** @param Collection<int,Balance>|LengthAwarePaginator $balances */
    public static function collect(Collection|LengthAwarePaginator $balances): DataCollection|PaginatedDataCollection
    {
        $isPaginated = $balances instanceof LengthAwarePaginator;

        $items = $isPaginated ? collect($balances->items()) : $balances;

        if ($items->isEmpty()) {
            return $isPaginated
                ? BalanceData::collect([], PaginatedDataCollection::class)
                : BalanceData::collect([]);
        }

        $ids = $items->pluck('id')->all();
        $reservedById = self::reservedByBalanceId($ids);

        $mapped = $items->map(fn (Balance $b) => self::makeData(
            $b,
            (int) ($reservedById[$b->id] ?? 0),
        ));

        if ($isPaginated) {
            // Rebuild a paginator-like collection for the data layer.
            return BalanceData::collect(
                new \Illuminate\Pagination\LengthAwarePaginator(
                    $mapped->all(),
                    $balances->total(),
                    $balances->perPage(),
                    $balances->currentPage(),
                    ['path' => $balances->path()],
                ),
                PaginatedDataCollection::class,
            );
        }

        return BalanceData::collect($mapped, DataCollection::class);
    }

    private static function makeData(Balance $balance, int $reserved, ?int $real = null): BalanceData
    {
        return BalanceData::from(array_merge(
            $balance->only([
                'id', 'user_id', 'name', 'description', 'initial_amount',
                'final_amount', 'reconciled_amount', 'reconciled_at', 'is_primary',
            ]),
            [
                // US-4 appended attributes aren't columns, so they must be passed
                // explicitly — without these every presented balance reads
                // drift=null / is_drift_flagged=false regardless of DB state.
                'reconciled_at' => $balance->reconciled_at?->toDateString(),
                'drift' => $balance->drift,
                'is_drift_flagged' => (bool) $balance->is_drift_flagged,
                'reserved' => $reserved,
                'real' => $real ?? ((int) $balance->final_amount - $reserved),
                'real_balance' => $real ?? ((int) $balance->final_amount - $reserved),
                'user' => $balance->relationLoaded('user') && $balance->user ? $balance->user : null,
                'transactions' => null,
            ],
        ));
    }

    /**
     * Reserved totals keyed by balance_id for a set of balances.
     *
     * Same definition as GetFundProgress::accumulated (contribution −
     * withdrawal, date-scoped to today) folded into one grouped query so list
     * surfaces don't issue one query per balance. Soft-deleted funds are
     * excluded — their reserves no longer exist.
     *
     * @param  array<int, int>  $balanceIds
     * @return array<int, int> balance_id => reserved
     */
    public static function reservedByBalanceId(array $balanceIds, ?CarbonImmutable $today = null): array
    {
        if ($balanceIds === []) {
            return [];
        }

        $day = ($today ?? now())->startOfDay()->toDateString();
        $rows = FundContribution::query()
            ->join('sinking_funds', 'sinking_funds.id', '=', 'fund_contributions.fund_id')
            ->whereIn('sinking_funds.from_balance_id', $balanceIds)
            ->whereNull('sinking_funds.deleted_at')
            ->whereDate('fund_contributions.date', '<=', $day)
            ->selectRaw(
                'sinking_funds.from_balance_id as bid, '
                .'COALESCE(SUM(CASE WHEN fund_contributions.type = ? THEN fund_contributions.amount ELSE 0 END), 0) - '
                .'COALESCE(SUM(CASE WHEN fund_contributions.type = ? THEN fund_contributions.amount ELSE 0 END), 0) as bal',
                ['contribution', 'withdrawal'],
            )
            ->groupBy('sinking_funds.from_balance_id')
            ->pluck('bal', 'bid')
            ->all();

        $out = array_fill_keys($balanceIds, 0);
        foreach ($rows as $bid => $bal) {
            $out[(int) $bid] = (int) $bal;
        }

        return $out;
    }
}
