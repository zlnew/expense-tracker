<?php

use App\Enums\CategoryType;
use App\Models\Budget;
use App\Models\BudgetItem;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);

    $this->category = Category::factory()->create([
        'user_id' => $this->user->id,
        'name' => 'Food & Groceries',
        'type' => CategoryType::EXPENSE,
    ]);

    $this->budget = Budget::factory()->create([
        'user_id' => $this->user->id,
        'period_start' => now()->startOfMonth(),
        'period_end' => now()->endOfMonth(),
        'cutoff_day' => 1,
        'is_active' => true,
    ]);

    $this->budgetItem = BudgetItem::factory()->create([
        'budget_id' => $this->budget->id,
        'category_id' => $this->category->id,
        'type' => CategoryType::EXPENSE,
        'planned_amount' => 1500000,
    ]);
});

test('budgets index renders budget list', function () {
    $response = $this->get(route('budgets.index'));

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('BudgetList')
            ->has('budgets.data', 1)
        );
});

test('budgets create renders form with categories and rollover preview', function () {
    $response = $this->get(route('budgets.create'));

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('BudgetCreate')
            ->has('categories')
            ->has('carryOverPreview')
        );
});

test('budget can be stored with envelope items', function () {
    $response = $this->post(route('budgets.store'), [
        'period_start' => now()->addMonth()->startOfMonth()->toDateString(),
        'period_end' => now()->addMonth()->endOfMonth()->toDateString(),
        'cutoff_day' => 1,
        'notes' => 'Next month plan',
        'items' => [
            [
                'category_id' => $this->category->id,
                'type' => 'expense',
                'planned_amount' => 2000000,
            ],
        ],
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('budgets', [
        'user_id' => $this->user->id,
        'notes' => 'Next month plan',
    ]);
});

test('budget show renders detail view with items', function () {
    $response = $this->get(route('budgets.show', $this->budget));

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('BudgetDetail')
            ->has('budget')
        );
});

test('budget edit renders edit form', function () {
    $response = $this->get(route('budgets.edit', $this->budget));

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('BudgetEdit')
            ->has('budget')
            ->has('categories')
        );
});

test('budget can be updated', function () {
    $response = $this->put(route('budgets.update', $this->budget), [
        'period_start' => $this->budget->period_start->toDateString(),
        'period_end' => $this->budget->period_end->toDateString(),
        'cutoff_day' => 5,
        'notes' => 'Updated notes',
        'items' => [
            [
                'category_id' => $this->category->id,
                'type' => 'expense',
                'planned_amount' => 1800000,
            ],
        ],
    ]);

    $response->assertRedirect();
    expect($this->budget->fresh()->cutoff_day)->toBe(5);
});

test('budget setActive activates target budget and deactivates others', function () {
    $secondBudget = Budget::factory()->create([
        'user_id' => $this->user->id,
        'period_start' => now()->addMonth()->startOfMonth(),
        'period_end' => now()->addMonth()->endOfMonth(),
        'is_active' => false,
    ]);

    $response = $this->post(route('budgets.set-active', $secondBudget));

    $response->assertRedirect();
    expect($secondBudget->fresh()->is_active)->toBeTrue()
        ->and($this->budget->fresh()->is_active)->toBeFalse();
});

test('budgets.transactions endpoint returns envelope-aware transaction drill down', function () {
    $response = $this->get(route('budgets.transactions', $this->budget));

    $response->assertOk()
        ->assertJsonStructure([
            'transactions',
            'fund' => [
                'reserved',
                'payout_transaction_ids',
            ],
        ]);
});

test('budget can be deleted', function () {
    $budgetToDelete = Budget::factory()->create([
        'user_id' => $this->user->id,
        'is_active' => false,
    ]);

    $response = $this->delete(route('budgets.destroy', $budgetToDelete));

    $response->assertRedirect();
    $this->assertDatabaseMissing('budgets', ['id' => $budgetToDelete->id]);
});

test('ownership middleware blocks unauthorized budget access', function () {
    $otherUser = User::factory()->create();
    $otherBudget = Budget::factory()->create(['user_id' => $otherUser->id]);

    $this->get(route('budgets.show', $otherBudget))->assertForbidden();
    $this->get(route('budgets.edit', $otherBudget))->assertForbidden();
    $this->post(route('budgets.set-active', $otherBudget))->assertForbidden();
    $this->get(route('budgets.transactions', $otherBudget))->assertForbidden();
    $this->delete(route('budgets.destroy', $otherBudget))->assertForbidden();
});
