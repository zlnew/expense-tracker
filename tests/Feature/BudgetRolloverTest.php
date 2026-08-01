<?php

use App\Actions\SaveBudget;
use App\DTO\BudgetData;
use App\Enums\CategoryType;
use App\Models\Balance;
use App\Models\Budget;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('new budget with carry_over adds previous cycle leftover to planned amounts', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $category = Category::factory()->for($user)->create(['name' => 'Food']);

    // Previous cycle: planned 500k, spent 300k => leftover 200k.
    $previous = Budget::factory()->for($user)->create([
        'period_start' => now()->startOfMonth()->subMonth(),
        'period_end' => now()->endOfMonth()->subMonth(),
        'cutoff_day' => 1,
        'carry_over' => true,
    ]);
    $prevItem = $previous->items()->create([
        'category_id' => $category->id,
        'type' => CategoryType::EXPENSE,
        'planned_amount' => 500_000,
    ]);

    $balance = Balance::factory()->for($user)->create(['name' => 'Cash', 'initial_amount' => 1_000_000, 'final_amount' => 700_000]);
    Transaction::factory()->for($user)->for($balance)->for($category)->create([
        'type' => CategoryType::EXPENSE,
        'amount' => 300_000,
        'budget_id' => $previous->id,
        'budget_item_id' => $prevItem->id,
        'date' => $previous->period_start,
    ]);

    // New cycle: planned 400k + leftover 200k = 600k.
    $budget = SaveBudget::run(new Budget, BudgetData::from([
        'period_start' => now()->startOfMonth(),
        'period_end' => now()->endOfMonth(),
        'cutoff_day' => 1,
        'carry_over' => true,
        'notes' => null,
        'items' => [
            ['category_id' => $category->id, 'type' => CategoryType::EXPENSE, 'planned_amount' => 400_000],
        ],
    ]));

    $budget->refresh();
    expect($budget->items()->first()->planned_amount)->toBe(600_000);
});

test('new budget without carry_over does not roll over', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $category = Category::factory()->for($user)->create(['name' => 'Food']);

    $previous = Budget::factory()->for($user)->create([
        'period_start' => now()->startOfMonth()->subMonth(),
        'period_end' => now()->endOfMonth()->subMonth(),
        'cutoff_day' => 1,
    ]);
    $prevItem = $previous->items()->create([
        'category_id' => $category->id,
        'type' => CategoryType::EXPENSE,
        'planned_amount' => 500_000,
    ]);

    $balance = Balance::factory()->for($user)->create(['name' => 'Cash', 'initial_amount' => 1_000_000, 'final_amount' => 700_000]);
    Transaction::factory()->for($user)->for($balance)->for($category)->create([
        'type' => CategoryType::EXPENSE,
        'amount' => 300_000,
        'budget_id' => $previous->id,
        'budget_item_id' => $prevItem->id,
        'date' => $previous->period_start,
    ]);

    $budget = SaveBudget::run(new Budget, BudgetData::from([
        'period_start' => now()->startOfMonth(),
        'period_end' => now()->endOfMonth(),
        'cutoff_day' => 1,
        'carry_over' => false,
        'notes' => null,
        'items' => [
            ['category_id' => $category->id, 'type' => CategoryType::EXPENSE, 'planned_amount' => 400_000],
        ],
    ]));

    $budget->refresh();
    expect($budget->items()->first()->planned_amount)->toBe(400_000);
});

test('overspent previous cycle rolls over zero (never negative)', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $category = Category::factory()->for($user)->create(['name' => 'Food']);

    $previous = Budget::factory()->for($user)->create([
        'period_start' => now()->startOfMonth()->subMonth(),
        'period_end' => now()->endOfMonth()->subMonth(),
        'cutoff_day' => 1,
    ]);
    $prevItem = $previous->items()->create([
        'category_id' => $category->id,
        'type' => CategoryType::EXPENSE,
        'planned_amount' => 100_000,
    ]);

    $balance = Balance::factory()->for($user)->create(['name' => 'Cash', 'initial_amount' => 1_000_000, 'final_amount' => 500_000]);
    Transaction::factory()->for($user)->for($balance)->for($category)->create([
        'type' => CategoryType::EXPENSE,
        'amount' => 500_000,
        'budget_id' => $previous->id,
        'budget_item_id' => $prevItem->id,
        'date' => $previous->period_start,
    ]);

    $budget = SaveBudget::run(new Budget, BudgetData::from([
        'period_start' => now()->startOfMonth(),
        'period_end' => now()->endOfMonth(),
        'cutoff_day' => 1,
        'carry_over' => true,
        'notes' => null,
        'items' => [
            ['category_id' => $category->id, 'type' => CategoryType::EXPENSE, 'planned_amount' => 400_000],
        ],
    ]));

    $budget->refresh();
    expect($budget->items()->first()->planned_amount)->toBe(400_000);
});
