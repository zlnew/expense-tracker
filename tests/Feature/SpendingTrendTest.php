<?php

use App\Actions\GetMonthlySpendingTrend;
use App\Models\Balance;
use App\Models\Budget;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('spending trend outer query does not re-apply the soft-delete scope', function () {
    $user = User::factory()->create();
    $budget = Budget::factory()->for($user)->create([
        'is_active' => true,
        'cutoff_day' => 25,
    ]);
    $balance = Balance::factory()->for($user)->create();

    Transaction::factory()->for($user)->for($balance)->create([
        'budget_id' => $budget->id,
        'date' => now()->startOfMonth()->addDay()->toDateString(),
    ]);

    // The action's SQL is Postgres-only (DATE_TRUNC, INTERVAL), so the default
    // sqlite suite cannot EXECUTE it. Assert on the compiled SQL instead: this
    // catches the T7 regression, where the outer Transaction::query() re-applied
    // the SoftDeletes scope against the aliased subquery and Postgres rejected
    // it with "missing FROM-clause entry for table transactions".
    $sql = (new GetMonthlySpendingTrend($user))->buildTrendQuery()->toSql();

    expect($sql)->toContain('cycle_date')
        ->and(substr_count($sql, 'deleted_at'))->toBe(1);
});
