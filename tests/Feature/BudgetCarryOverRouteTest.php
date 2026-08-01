<?php

use App\Enums\CategoryType;
use App\Models\Balance;
use App\Models\Budget;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutMiddleware(PreventRequestForgery::class);
});

test('carry_over checkbox value reaches the backend via the store route', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $category = Category::factory()->for($user)->create(['name' => 'Food']);

    // Previous cycle: 500k planned, 300k spent => 200k leftover.
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

    // POST the exact payload BudgetCreate sends (carry_over: true).
    $this->post(route('budgets.store'), [
        'period_start' => now()->startOfMonth()->toDateString(),
        'period_end' => now()->endOfMonth()->toDateString(),
        'cutoff_day' => 1,
        'carry_over' => true,
        'notes' => null,
        'items' => [
            ['category_id' => $category->id, 'type' => CategoryType::EXPENSE->value, 'planned_amount' => 400_000],
        ],
    ])->assertRedirect();

    $budget = Budget::where('user_id', $user->id)->latest('id')->first();

    expect($budget->carry_over)->toBeTrue();
    expect($budget->items()->first()->planned_amount)->toBe(600_000);
});

test('carry_over false via route does not roll over', function () {
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

    $this->post(route('budgets.store'), [
        'period_start' => now()->startOfMonth()->toDateString(),
        'period_end' => now()->endOfMonth()->toDateString(),
        'cutoff_day' => 1,
        'carry_over' => false,
        'notes' => null,
        'items' => [
            ['category_id' => $category->id, 'type' => CategoryType::EXPENSE->value, 'planned_amount' => 400_000],
        ],
    ])->assertRedirect();

    $budget = Budget::where('user_id', $user->id)->latest('id')->first();

    expect($budget->carry_over)->toBeFalse();
    expect($budget->items()->first()->planned_amount)->toBe(400_000);
});
