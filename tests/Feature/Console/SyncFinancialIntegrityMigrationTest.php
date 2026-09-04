<?php

use App\Enums\CategoryType;
use App\Models\Balance;
use App\Models\Budget;
use App\Models\BudgetItem;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('integrity migration automatically synchronizes balances and prunes orphaned envelopes', function () {
    $user = User::factory()->create();
    $balance = Balance::factory()->for($user)->create([
        'initial_amount' => 1_000_000,
        'final_amount' => 1_000_000, // Should become 700_000 after sync
    ]);
    $cat = Category::factory()->for($user)->create(['type' => CategoryType::EXPENSE]);
    $budget = Budget::factory()->for($user)->create(['is_active' => true]);

    // Active 0-planned envelope (has a transaction)
    $activeItem = BudgetItem::factory()->for($budget)->for($cat)->create([
        'planned_amount' => 0,
    ]);
    Transaction::factory()->for($user)->create([
        'balance_id' => $balance->id,
        'category_id' => $cat->id,
        'budget_id' => $budget->id,
        'budget_item_id' => $activeItem->id,
        'type' => 'expense',
        'amount' => 300_000,
    ]);

    // Orphaned 0-planned envelope (0 transactions)
    $orphanedItem = BudgetItem::factory()->for($budget)->for($cat)->create([
        'planned_amount' => 0,
    ]);

    // Execute the migration directly
    $migration = require database_path('migrations/2026_09_04_180000_sync_ledger_integrity_and_prune_orphaned_budget_items.php');
    $migration->up();

    // Verify balance resynced: 1,000,000 - 300,000 = 700,000
    expect($balance->fresh()->final_amount)->toBe(700_000);

    // Verify orphaned envelope was deleted
    expect(BudgetItem::find($orphanedItem->id))->toBeNull();

    // Verify active envelope with transaction was retained
    expect(BudgetItem::find($activeItem->id))->not->toBeNull();
});
