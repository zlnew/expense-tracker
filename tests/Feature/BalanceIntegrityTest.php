<?php

use App\Actions\DeleteTransaction;
use App\Actions\SyncBalance;
use App\Enums\CategoryType;
use App\Models\Balance;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\RecordingLockGrammar;
uses(RefreshDatabase::class);

it('computes the balance with a single aggregate query', function () {
    $user = User::factory()->create();
    $balance = Balance::factory()->for($user)->create(['initial_amount' => 100_000]);

    Transaction::factory()->count(50)->for($user)->for($balance)->create([
        'type' => CategoryType::INCOME,
        'amount' => 1_000,
    ]);
    Transaction::factory()->count(30)->for($user)->for($balance)->create([
        'type' => CategoryType::EXPENSE,
        'amount' => 500,
    ]);

    DB::enableQueryLog();
    SyncBalance::run($balance->id);
    $queries = DB::getQueryLog();
    DB::disableQueryLog();

    // 100_000 + (50 * 1_000) - (30 * 500) = 135_000
    expect($balance->fresh()->final_amount)->toBe(135_000);

    // The aggregate must not scale with row count: a handful of queries,
    // never one-per-transaction and never a full SELECT of every row.
    expect(count($queries))->toBeLessThan(6);

    $selects = collect($queries)->filter(
        fn ($q) => str_contains(strtolower($q['query']), 'select')
            && str_contains(strtolower($q['query']), 'transactions')
    );
    foreach ($selects as $q) {
        expect(strtolower($q['query']))->toContain('sum(');
    }
});

it('locks the balance row while recomputing', function () {
    $user = User::factory()->create();
    $balance = Balance::factory()->for($user)->create(['initial_amount' => 0]);

    $grammar = new RecordingLockGrammar(DB::connection());
    DB::connection()->setQueryGrammar($grammar);

    DB::transaction(fn () => SyncBalance::run($balance->id));

    expect($grammar->lockRequested)->toBeTrue(
        'SyncBalance must request SELECT ... FOR UPDATE on the balance row before writing final_amount'
    );
});

it('soft-deletes transactions and excludes them from the balance', function () {
    $user = User::factory()->create();
    $balance = Balance::factory()->for($user)->create(['initial_amount' => 0]);

    $keep = Transaction::factory()->for($user)->for($balance)->create([
        'type' => CategoryType::INCOME,
        'amount' => 30_000,
    ]);
    $remove = Transaction::factory()->for($user)->for($balance)->create([
        'type' => CategoryType::INCOME,
        'amount' => 70_000,
    ]);

    SyncBalance::run($balance->id);
    expect($balance->fresh()->final_amount)->toBe(100_000);

    DeleteTransaction::run($remove);

    // Row still exists for audit...
    expect(Transaction::withTrashed()->find($remove->id))->not->toBeNull();
    expect(Transaction::find($remove->id))->toBeNull();

    // ...but no longer counts toward the balance.
    expect($balance->fresh()->final_amount)->toBe(30_000);
});

it('paginates transactions on the balance detail page', function () {
    $user = User::factory()->create();
    $balance = Balance::factory()->for($user)->create();

    Transaction::factory()->count(75)->for($user)->for($balance)->create();

    $this->actingAs($user)
        ->get(route('balances.show', $balance))
        ->assertInertia(fn ($page) => $page
            ->has('transactions.data', 25)
            ->has('transactions.meta')
        );
});
