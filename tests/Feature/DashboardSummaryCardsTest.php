<?php

use App\Actions\GetSummaryCardsData;
use App\Models\Balance;
use App\Models\Budget;
use App\Models\BudgetItem;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('summary cards report no active budget when none exists', function () {
    $user = User::factory()->create();

    $cards = GetSummaryCardsData::run($user);

    expect($cards['has_active_budget'])->toBeFalse()
        ->and($cards['active_budget_id'])->toBeNull();
});

test('summary cards report the active budget when one exists', function () {
    $user = User::factory()->create();
    $budget = Budget::factory()->for($user)->create([
        'is_active' => true,
    ]);
    $inactive = Budget::factory()->for($user)->create([
        'is_active' => false,
    ]);

    $cards = GetSummaryCardsData::run($user);

    expect($cards['has_active_budget'])->toBeTrue()
        ->and($cards['active_budget_id'])->toBe($budget->id)
        ->and($cards['active_budget_id'])->not->toBe($inactive->id);
});

test('summary cards compute budget remaining against the active budget', function () {
    $user = User::factory()->create();
    $budget = Budget::factory()->for($user)->create([
        'is_active' => true,
        'period_start' => now()->startOfMonth(),
        'period_end' => now()->endOfMonth(),
        'cutoff_day' => 1,
    ]);
    $balance = Balance::factory()->for($user)->create();
    $category = Category::factory()->for($user)->create();

    $item = BudgetItem::factory()->create([
        'budget_id' => $budget->id,
        'category_id' => $category->id,
        'planned_amount' => 1_000_000,
    ]);

    Transaction::factory()->for($user)->for($balance)->create([
        'budget_id' => $budget->id,
        'budget_item_id' => $item->id,
        'category_id' => $category->id,
        'type' => 'expense',
        'amount' => 400_000,
        'date' => now()->toDateString(),
    ]);

    $cards = GetSummaryCardsData::run($user);

    expect($cards['has_active_budget'])->toBeTrue()
        ->and($cards['budget_remaining'])->toBe(600_000);
});
