<?php

namespace App\Queries;

use App\Enums\CategoryType;
use App\Models\Budget;
use App\Models\Transaction;
use App\Models\User;
use App\Support\BudgetCycle;
use Illuminate\Support\Facades\DB;

class GetMonthlySpendingTrend extends Query
{
    public readonly User $user;

    private readonly ?Budget $activeBudget;

    public function __construct(User|int $user)
    {
        $this->user = $user instanceof User
            ? $user
            : User::query()->findOrFail($user);

        $this->activeBudget = Budget::query()
            ->where('user_id', $this->user->id)
            ->where('is_active', true)
            ->first();
    }

    /**
     * Build the trend query without executing it (extracted for testability:
     * the SQL is Postgres-only, so tests assert on the compiled SQL shape).
     */
    public function buildTrendQuery()
    {
        $cutoffDay = $this->activeBudget->cutoff_day;
        $currentYear = now()->year;
        $cycleDateSql = BudgetCycle::cycleDateSql('date', '?');

        $subquery = Transaction::query()
            ->selectRaw(
                "*,
                {$cycleDateSql} AS cycle_date
            ",
                [$cutoffDay],
            )
            ->where('user_id', $this->user->id)
            ->where('budget_id', $this->activeBudget->id)
            ->excludeInternalTransfers();

        // Plain DB builder: Transaction::query() would re-apply the SoftDeletes
        // scope against the outer FROM (the aliased subquery), producing
        // "missing FROM-clause entry for table transactions" on Postgres.
        // The inner subquery already filters deleted rows.
        return DB::query()
            ->fromSub($subquery, 'txn')
            ->selectRaw('
                '.BudgetCycle::extractMonthSql('cycle_date').' AS month,
                type,
                SUM(amount) AS total_amount
            ')
            ->whereRaw(BudgetCycle::extractYearSql('cycle_date').' = ?', [$currentYear])
            ->groupBy('month', 'type');
    }

    public function handle(): array
    {
        if ($this->activeBudget === null) {
            return [];
        }

        $transactions = $this->buildTrendQuery()->get();

        $grouped = $transactions->groupBy('month');

        return collect(range(1, 12))
            ->map(function (int $month) use ($grouped): array {
                $items = $grouped->get($month, collect());

                $income = (int) (
                    $items->firstWhere('type', CategoryType::INCOME->value)?->total_amount ?? 0
                );

                $expense = (int) (
                    $items->firstWhere('type', CategoryType::EXPENSE->value)?->total_amount ?? 0
                );

                return [
                    'month' => $month,
                    'income' => $income,
                    'expense' => $expense,
                ];
            })
            ->toArray();
    }
}
