<?php

use App\Actions\GetBudgetProgress;
use App\Enums\CategoryType;
use App\Models\Balance;
use App\Models\Budget;
use App\Models\BudgetItem;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use App\Queries\TransactionQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function apiToken(User $user, string $abilities): string
{
    return $user->createToken('test', explode(',', $abilities))->plainTextToken;
}

test('unauthenticated requests to every api endpoint return 401', function () {
    $this->getJson('/api/transactions')->assertUnauthorized();
    $this->postJson('/api/transactions', [])->assertUnauthorized();
    $this->patchJson('/api/transactions/1')->assertUnauthorized();
    $this->getJson('/api/categories')->assertUnauthorized();
    $this->getJson('/api/balances')->assertUnauthorized();
});

test('a token only sees its own transactions, categories, and balances', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();

    $ownerCategory = Category::factory()->for($owner)->create(['name' => 'Owner Food', 'type' => CategoryType::EXPENSE]);
    $ownerBalance = Balance::factory()->for($owner)->create(['name' => 'Owner Cash', 'initial_amount' => 100_000, 'final_amount' => 100_000]);
    Transaction::factory()->for($owner)->for($ownerBalance)->for($ownerCategory)->create([
        'amount' => 25_000,
        'type' => CategoryType::EXPENSE,
        'description' => 'owner-secret',
    ]);

    $intruderCategory = Category::factory()->for($intruder)->create(['name' => 'Intruder Category', 'type' => CategoryType::EXPENSE]);
    $intruderBalance = Balance::factory()->for($intruder)->create(['name' => 'Intruder Cash', 'initial_amount' => 0, 'final_amount' => 0]);
    Transaction::factory()->for($intruder)->for($intruderBalance)->for($intruderCategory)->create([
        'amount' => 1_000,
        'type' => CategoryType::EXPENSE,
        'description' => 'intruder-secret',
    ]);

    $token = apiToken($owner, 'transactions:read,categories:read,balances:read');

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/transactions')
        ->assertOk()
        ->assertJsonPath('0.description', 'owner-secret')
        ->assertJsonMissing(['description' => 'intruder-secret'])
        ->assertJsonCount(1);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/categories')
        ->assertOk()
        ->assertJsonPath('0.name', 'Owner Food')
        ->assertJsonMissing(['name' => 'Intruder Category']);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/balances')
        ->assertOk()
        ->assertJsonPath('0.name', 'Owner Cash')
        ->assertJsonMissing(['name' => 'Intruder Cash']);
});

test('token abilities are enforced per endpoint', function () {
    $user = User::factory()->create();
    $balance = Balance::factory()->for($user)->create(['initial_amount' => 100_000, 'final_amount' => 100_000]);

    // Sanctum::actingAs per request avoids Laravel's RequestGuard user cache
    // leaking the previous request's identity inside a single test.
    $payload = [
        'balance_id' => $balance->id,
        'type' => CategoryType::EXPENSE->value,
        'date' => now()->toDateString(),
        'amount' => 10_000,
        'description' => 'nope',
    ];

    Sanctum::actingAs($user, ['transactions:read']);
    $this->postJson('/api/transactions', $payload)->assertForbidden();

    Sanctum::actingAs($user, ['transactions:write']);
    $this->getJson('/api/transactions')->assertForbidden();
});

test('posting a transaction creates it, syncs the balance, and it shows in the web query path', function () {
    $user = User::factory()->create();
    $balance = Balance::factory()->for($user)->create(['initial_amount' => 100_000, 'final_amount' => 100_000]);
    $category = Category::factory()->for($user)->create(['name' => 'Food', 'type' => CategoryType::EXPENSE]);

    $token = apiToken($user, 'transactions:write');

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/transactions', [
            'balance_id' => $balance->id,
            'category_id' => $category->id,
            'type' => CategoryType::EXPENSE->value,
            'date' => '2026-08-12',
            'amount' => 25_000,
            'description' => 'lunch',
        ])
        ->assertCreated()
        // Bare TransactionData resource (no `data` wrapper) per the API spec.
        ->assertJsonPath('description', 'lunch')
        ->assertJsonPath('type', CategoryType::EXPENSE->value)
        ->assertJsonPath('amount', 25_000)
        ->assertJsonPath('balance.id', $balance->id)
        ->assertJsonPath('category.id', $category->id);

    $this->assertDatabaseHas('transactions', [
        'user_id' => $user->id,
        'balance_id' => $balance->id,
        'category_id' => $category->id,
        'type' => CategoryType::EXPENSE->value,
        'amount' => 25_000,
        'description' => 'lunch',
    ]);

    expect($balance->fresh()->final_amount)->toBe(75_000);

    // The row must be visible through the same user-scoped query the web UI uses.
    $visible = TransactionQuery::make()->forUser($user->id)->get();
    expect($visible->pluck('description'))->toContain('lunch');
});

test('posting a transaction validates type, amount, and scoped foreign keys', function () {
    $user = User::factory()->create();
    $intruder = User::factory()->create();
    $balance = Balance::factory()->for($user)->create(['initial_amount' => 100_000, 'final_amount' => 100_000]);
    $intruderBalance = Balance::factory()->for($intruder)->create(['initial_amount' => 0, 'final_amount' => 0]);
    $intruderCategory = Category::factory()->for($intruder)->create(['name' => 'Foreign', 'type' => CategoryType::EXPENSE]);

    $token = apiToken($user, 'transactions:write');

    // bad type
    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/transactions', [
            'balance_id' => $balance->id,
            'type' => 'cash',
            'date' => now()->toDateString(),
            'amount' => 10_000,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('type');

    // amount: zero, negative, non-integer
    foreach ([0, -100, 'abc'] as $amount) {
        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/transactions', [
                'balance_id' => $balance->id,
                'type' => CategoryType::EXPENSE->value,
                'date' => now()->toDateString(),
                'amount' => $amount,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('amount');
    }

    // missing balance_id
    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/transactions', [
            'type' => CategoryType::EXPENSE->value,
            'date' => now()->toDateString(),
            'amount' => 10_000,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('balance_id');

    // foreign-user balance + category must be rejected by the scoped exists rules
    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/transactions', [
            'balance_id' => $intruderBalance->id,
            'category_id' => $intruderCategory->id,
            'type' => CategoryType::EXPENSE->value,
            'date' => now()->toDateString(),
            'amount' => 10_000,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['balance_id', 'category_id']);
});

test('cors header equals the app origin and never wildcard', function () {
    $allowed = 'https://et.aprizqyhub.my.id';

    $this->withHeader('Origin', $allowed)
        ->getJson('/api/transactions')
        ->assertHeader('access-control-allow-origin', $allowed);

    // Fruitcake's single-origin shortcut always emits the whitelisted origin
    // on matching paths (never `*`, never the requesting origin). What must
    // hold for a foreign origin: ACAO != foreign origin and != wildcard.
    $response = $this->withHeader('Origin', 'https://evil.example')
        ->getJson('/api/transactions');

    $acaO = $response->headers->get('access-control-allow-origin');
    expect($acaO)->not->toBe('https://evil.example')
        ->and($acaO)->not->toBe('*')
        ->and($acaO)->toBe($allowed);
});

test('is_paginate=true returns the paginator shape', function () {
    $user = User::factory()->create();
    $balance = Balance::factory()->for($user)->create(['initial_amount' => 0, 'final_amount' => 0]);
    Transaction::factory()->count(15)->for($user)->for($balance)->create();

    $token = apiToken($user, 'transactions:read');

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/transactions?is_paginate=true&per_page=5')
        ->assertOk()
        ->assertJsonStructure([
            'data',
            'links',
            'meta' => ['current_page', 'last_page', 'per_page', 'total'],
        ])
        ->assertJsonCount(5, 'data');
});

test('patching a transaction updates its budget link and budget progress reflects it', function () {
    $user = User::factory()->create();
    $balance = Balance::factory()->for($user)->create(['initial_amount' => 100_000, 'final_amount' => 100_000]);
    $category = Category::factory()->for($user)->create(['name' => 'Food', 'type' => CategoryType::EXPENSE]);
    $budget = Budget::factory()->for($user)->create([
        'period_start' => now()->startOfMonth(),
        'period_end' => now()->endOfMonth(),
        'cutoff_day' => 25,
        'is_active' => true,
    ]);
    $item = BudgetItem::factory()->for($budget)->for($category)->create([
        'type' => CategoryType::EXPENSE,
        'planned_amount' => 500_000,
    ]);

    $transaction = Transaction::factory()->for($user)->for($balance)->for($category)->create([
        'type' => CategoryType::EXPENSE,
        'amount' => 25_000,
        'date' => now(),
        'description' => 'lunch',
    ]);

    expect($transaction->budget_id)->toBeNull();

    $token = apiToken($user, 'transactions:write');

    $this->withHeader('Authorization', "Bearer {$token}")
        ->patchJson("/api/transactions/{$transaction->id}", [
            'budget_id' => $budget->id,
            'budget_item_id' => $item->id,
        ])
        ->assertOk()
        ->assertJsonPath('id', $transaction->id)
        ->assertJsonPath('budget_id', $budget->id)
        ->assertJsonPath('budget_item_id', $item->id);

    $this->assertDatabaseHas('transactions', [
        'id' => $transaction->id,
        'budget_id' => $budget->id,
        'budget_item_id' => $item->id,
    ]);

    // The same spent-to-date computation the budgets endpoint uses now
    // includes the patched transaction.
    $progress = GetBudgetProgress::run($user)->items();
    $foodItem = collect($progress)->firstWhere('category_id', $category->id);
    expect($foodItem->actual_amount)->toBe(25_000);
});

test('patching a transaction derives a missing budget id from its budget item', function () {
    $user = User::factory()->create();
    $balance = Balance::factory()->for($user)->create(['initial_amount' => 100_000, 'final_amount' => 100_000]);
    $category = Category::factory()->for($user)->create(['name' => 'Food', 'type' => CategoryType::EXPENSE]);
    $budget = Budget::factory()->for($user)->create([
        'period_start' => now()->startOfMonth(),
        'period_end' => now()->endOfMonth(),
        'cutoff_day' => 25,
        'is_active' => true,
    ]);
    $item = BudgetItem::factory()->for($budget)->for($category)->create([
        'type' => CategoryType::EXPENSE,
        'planned_amount' => 500_000,
    ]);

    $transaction = Transaction::factory()->for($user)->for($balance)->for($category)->create([
        'type' => CategoryType::EXPENSE,
        'amount' => 10_000,
        'date' => now(),
    ]);

    // The live root cause: budget_item_id set, budget_id still null.
    $transaction->forceFill(['budget_id' => null, 'budget_item_id' => $item->id])->save();

    $token = apiToken($user, 'transactions:write');

    // A PATCH that touches nothing budget-related still heals the row.
    $this->withHeader('Authorization', "Bearer {$token}")
        ->patchJson("/api/transactions/{$transaction->id}", [
            'description' => 'fixed',
        ])
        ->assertOk()
        ->assertJsonPath('budget_id', $budget->id)
        ->assertJsonPath('budget_item_id', $item->id);

    $this->assertDatabaseHas('transactions', [
        'id' => $transaction->id,
        'budget_id' => $budget->id,
        'budget_item_id' => $item->id,
        'description' => 'fixed',
    ]);
});

test('patching with explicit null budget fields detaches the budget link', function () {
    $user = User::factory()->create();
    $balance = Balance::factory()->for($user)->create(['initial_amount' => 100_000, 'final_amount' => 100_000]);
    $category = Category::factory()->for($user)->create(['name' => 'Food', 'type' => CategoryType::EXPENSE]);
    $budget = Budget::factory()->for($user)->create([
        'period_start' => now()->startOfMonth(),
        'period_end' => now()->endOfMonth(),
        'cutoff_day' => 25,
        'is_active' => true,
    ]);
    $item = BudgetItem::factory()->for($budget)->for($category)->create([
        'type' => CategoryType::EXPENSE,
        'planned_amount' => 500_000,
    ]);

    // A June-style historical row that IS linked to the active budget + item.
    $transaction = Transaction::factory()->for($user)->for($balance)->for($category)->create([
        'type' => CategoryType::EXPENSE,
        'amount' => 25_000,
        'date' => now()->subMonths(2),
    ]);
    $transaction->forceFill(['budget_id' => $budget->id, 'budget_item_id' => $item->id])->save();

    $token = apiToken($user, 'transactions:write');

    // Explicit null + null = detach (spec §8.1): the reclassification recipe.
    // Must NOT resurrect the active budget onto this historical row.
    $this->withHeader('Authorization', "Bearer {$token}")
        ->patchJson("/api/transactions/{$transaction->id}", [
            'budget_id' => null,
            'budget_item_id' => null,
        ])
        ->assertOk()
        ->assertJsonPath('budget_id', null)
        ->assertJsonPath('budget_item_id', null);

    $this->assertDatabaseHas('transactions', [
        'id' => $transaction->id,
        'budget_id' => null,
        'budget_item_id' => null,
    ]);

    // A later patch WITHOUT explicit nulls re-links via rule b (nulls
    // resurrect the active budget) — the amendment only protects the explicit
    // detach body, exactly as the data-fix recipe requires.
    $this->withHeader('Authorization', "Bearer {$token}")
        ->patchJson("/api/transactions/{$transaction->id}", [
            'description' => 'still linked',
        ])
        ->assertOk()
        ->assertJsonPath('budget_id', $budget->id)
        ->assertJsonPath('budget_item_id', $item->id);
});

test('a token cannot patch another user\'s transaction', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $intruderBalance = Balance::factory()->for($intruder)->create(['initial_amount' => 0, 'final_amount' => 0]);
    $intruderTransaction = Transaction::factory()->for($intruder)->for($intruderBalance)->create();

    $token = apiToken($owner, 'transactions:write');

    $this->withHeader('Authorization', "Bearer {$token}")
        ->patchJson("/api/transactions/{$intruderTransaction->id}", [
            'description' => 'hijacked',
        ])
        ->assertNotFound();

    $this->assertDatabaseHas('transactions', [
        'id' => $intruderTransaction->id,
        'description' => $intruderTransaction->description,
    ]);
});

test('patching a transaction requires the write ability', function () {
    $user = User::factory()->create();
    $balance = Balance::factory()->for($user)->create(['initial_amount' => 0, 'final_amount' => 0]);
    $transaction = Transaction::factory()->for($user)->for($balance)->create();

    Sanctum::actingAs($user, ['transactions:read']);
    $this->patchJson("/api/transactions/{$transaction->id}", ['description' => 'nope'])->assertForbidden();
});

test('patching a transaction validates scoped foreign keys', function () {
    $user = User::factory()->create();
    $intruder = User::factory()->create();
    $balance = Balance::factory()->for($user)->create(['initial_amount' => 0, 'final_amount' => 0]);
    $transaction = Transaction::factory()->for($user)->for($balance)->create();
    $intruderBudget = Budget::factory()->for($intruder)->create(['is_active' => true]);
    $intruderCategory = Category::factory()->for($intruder)->create(['name' => 'Foreign', 'type' => CategoryType::EXPENSE]);
    $intruderItem = BudgetItem::factory()->for($intruderBudget)->for($intruderCategory)->create(['type' => CategoryType::EXPENSE]);

    $token = apiToken($user, 'transactions:write');

    $this->withHeader('Authorization', "Bearer {$token}")
        ->patchJson("/api/transactions/{$transaction->id}", [
            'budget_item_id' => $intruderItem->id,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('budget_item_id');

    $this->withHeader('Authorization', "Bearer {$token}")
        ->patchJson("/api/transactions/{$transaction->id}", [
            'budget_id' => $intruderBudget->id,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('budget_id');
});

test('posting a transaction auto-links the active budget item for its category', function () {
    $user = User::factory()->create();
    $balance = Balance::factory()->for($user)->create(['initial_amount' => 100_000, 'final_amount' => 100_000]);
    $category = Category::factory()->for($user)->create(['name' => 'Food', 'type' => CategoryType::EXPENSE]);
    $budget = Budget::factory()->for($user)->create([
        'period_start' => now()->startOfMonth(),
        'period_end' => now()->endOfMonth(),
        'cutoff_day' => 25,
        'is_active' => true,
    ]);
    $item = BudgetItem::factory()->for($budget)->for($category)->create([
        'type' => CategoryType::EXPENSE,
        'planned_amount' => 500_000,
    ]);

    $token = apiToken($user, 'transactions:write');

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/transactions', [
            'balance_id' => $balance->id,
            'category_id' => $category->id,
            'type' => CategoryType::EXPENSE->value,
            'date' => now()->toDateString(),
            'amount' => 30_000,
            'description' => 'auto-linked',
        ])
        ->assertCreated()
        ->assertJsonPath('budget_id', $budget->id)
        ->assertJsonPath('budget_item_id', $item->id);

    $this->assertDatabaseHas('transactions', [
        'user_id' => $user->id,
        'budget_id' => $budget->id,
        'budget_item_id' => $item->id,
        'amount' => 30_000,
        'description' => 'auto-linked',
    ]);

    // Budget actual_amount for the category now includes the API-created row.
    $progress = GetBudgetProgress::run($user)->items();
    $foodItem = collect($progress)->firstWhere('category_id', $category->id);
    expect($foodItem->actual_amount)->toBe(30_000);
});

test('posting a transaction with a budget item but no budget id derives the budget', function () {
    $user = User::factory()->create();
    $balance = Balance::factory()->for($user)->create(['initial_amount' => 100_000, 'final_amount' => 100_000]);
    $category = Category::factory()->for($user)->create(['name' => 'Transport', 'type' => CategoryType::EXPENSE]);
    $budget = Budget::factory()->for($user)->create([
        'period_start' => now()->startOfMonth(),
        'period_end' => now()->endOfMonth(),
        'cutoff_day' => 25,
        'is_active' => true,
    ]);
    $item = BudgetItem::factory()->for($budget)->for($category)->create([
        'type' => CategoryType::EXPENSE,
        'planned_amount' => 500_000,
    ]);

    $token = apiToken($user, 'transactions:write');

    // Cogsworth's live bug: sends budget_item_id but no budget_id.
    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/transactions', [
            'balance_id' => $balance->id,
            'category_id' => $category->id,
            'budget_item_id' => $item->id,
            'type' => CategoryType::EXPENSE->value,
            'date' => now()->toDateString(),
            'amount' => 48_000,
        ])
        ->assertCreated()
        ->assertJsonPath('budget_id', $budget->id)
        ->assertJsonPath('budget_item_id', $item->id);

    $this->assertDatabaseHas('transactions', [
        'user_id' => $user->id,
        'budget_id' => $budget->id,
        'budget_item_id' => $item->id,
        'amount' => 48_000,
    ]);
});

test('posting a transaction without an active budget leaves the budget link null', function () {
    $user = User::factory()->create();
    $balance = Balance::factory()->for($user)->create(['initial_amount' => 100_000, 'final_amount' => 100_000]);
    $category = Category::factory()->for($user)->create(['name' => 'Food', 'type' => CategoryType::EXPENSE]);

    $token = apiToken($user, 'transactions:write');

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/transactions', [
            'balance_id' => $balance->id,
            'category_id' => $category->id,
            'type' => CategoryType::EXPENSE->value,
            'date' => now()->toDateString(),
            'amount' => 25_000,
        ])
        ->assertCreated()
        ->assertJsonPath('budget_id', null)
        ->assertJsonPath('budget_item_id', null);
});
