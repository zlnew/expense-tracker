<?php

use App\Actions\SaveTransaction;
use App\DTO\TransactionData;
use App\Enums\CategoryType;
use App\Models\Balance;
use App\Models\Budget;
use App\Models\BudgetItem;
use App\Models\Category;
use App\Models\SinkingFund;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('sync financial integrity command runs successfully in dry-run mode without modifying data', function () {
    $user = User::factory()->create();
    $balance = Balance::factory()->for($user)->create([
        'initial_amount' => 100_000,
        'final_amount' => 100_000,
    ]);

    $category = Category::factory()->for($user)->create(['type' => CategoryType::EXPENSE]);

    SaveTransaction::run(new Transaction, TransactionData::from([
        'user_id' => $user->id,
        'balance_id' => $balance->id,
        'category_id' => $category->id,
        'type' => CategoryType::EXPENSE->value,
        'date' => now()->toDateString(),
        'amount' => 30_000,
        'description' => 'Coffee',
    ]));

    // Artificially corrupt the final_amount
    $balance->update(['final_amount' => 999_999]);

    $this->artisan('app:sync-financial-integrity', ['--dry-run' => true])
        ->expectsOutputToContain('Expense Tracker Financial Integrity Audit')
        ->expectsOutputToContain('DRY-RUN')
        ->expectsOutputToContain('DISCREPANCY (Dry Run)')
        ->assertSuccessful();

    // Verify record was NOT modified in dry-run mode
    expect($balance->fresh()->final_amount)->toBe(999_999);
});

test('sync financial integrity command fixes balance discrepancy when not in dry-run mode', function () {
    $user = User::factory()->create();
    $balance = Balance::factory()->for($user)->create([
        'initial_amount' => 100_000,
        'final_amount' => 100_000,
    ]);

    $category = Category::factory()->for($user)->create(['type' => CategoryType::EXPENSE]);

    SaveTransaction::run(new Transaction, TransactionData::from([
        'user_id' => $user->id,
        'balance_id' => $balance->id,
        'category_id' => $category->id,
        'type' => CategoryType::EXPENSE->value,
        'date' => now()->toDateString(),
        'amount' => 25_000,
        'description' => 'Snack',
    ]));

    // Artificially corrupt final_amount
    $balance->update(['final_amount' => 50_000]);

    $this->artisan('app:sync-financial-integrity', ['--user' => $user->id])
        ->expectsOutputToContain('FIXED (Resynced)')
        ->assertSuccessful();

    // Verify final_amount was resynced to 100_000 - 25_000 = 75_000
    expect($balance->fresh()->final_amount)->toBe(75_000);
});

test('sync financial integrity command prunes orphaned zero planned budget items', function () {
    $user = User::factory()->create();
    $budget = Budget::factory()->for($user)->create(['is_active' => true]);
    $cat1 = Category::factory()->for($user)->create(['type' => CategoryType::EXPENSE]);
    $cat2 = Category::factory()->for($user)->create(['type' => CategoryType::EXPENSE]);

    // Orphaned zero-planned item (no transactions linked)
    $orphanedItem = BudgetItem::factory()->for($budget)->for($cat1)->create([
        'planned_amount' => 0,
        'type' => CategoryType::EXPENSE,
    ]);

    // Active zero-planned item (has a linked transaction)
    $activeItem = BudgetItem::factory()->for($budget)->for($cat2)->create([
        'planned_amount' => 0,
        'type' => CategoryType::EXPENSE,
    ]);

    $balance = Balance::factory()->for($user)->create();
    Transaction::factory()->for($user)->create([
        'balance_id' => $balance->id,
        'budget_id' => $budget->id,
        'budget_item_id' => $activeItem->id,
        'category_id' => $cat2->id,
        'amount' => 15_000,
    ]);

    $this->artisan('app:sync-financial-integrity', ['--prune-zero-budget-items' => true])
        ->expectsOutputToContain('1 orphaned item(s) successfully pruned')
        ->assertSuccessful();

    // Orphaned item should be deleted
    expect(BudgetItem::find($orphanedItem->id))->toBeNull();

    // Active item with transactions should be preserved
    expect(BudgetItem::find($activeItem->id))->not->toBeNull();
});

test('sync financial integrity command audits sinking funds and reports progress', function () {
    $user = User::factory()->create();
    $balance = Balance::factory()->for($user)->create();
    $fund = SinkingFund::factory()->for($user)->create([
        'name' => 'Car Service',
        'from_balance_id' => $balance->id,
        'target_amount' => 500_000,
    ]);

    $fund->contributions()->create([
        'user_id' => $user->id,
        'amount' => 250_000,
        'date' => now()->toDateString(),
        'type' => 'contribution',
    ]);

    $this->artisan('app:sync-financial-integrity', ['--user' => $user->id])
        ->expectsOutputToContain('Car Service')
        ->assertSuccessful();
});
