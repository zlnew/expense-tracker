<?php

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
    $this->getJson('/api/categories')->assertUnauthorized();
    $this->getJson('/api/balances')->assertUnauthorized();
    $this->getJson('/api/budgets')->assertUnauthorized();
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

    Sanctum::actingAs($user, ['transactions:read']);
    $this->getJson('/api/budgets')->assertForbidden();
});

test('a token only sees its own budgets with items, limits, and categories', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();

    $ownerCategory = Category::factory()->for($owner)->create(['name' => 'Owner Food', 'type' => CategoryType::EXPENSE]);
    $ownerBudget = Budget::factory()->for($owner)->create([
        'period_start' => now()->startOfMonth(),
        'period_end' => now()->endOfMonth(),
        'cutoff_day' => 25,
        'is_active' => true,
    ]);
    BudgetItem::factory()->for($ownerBudget)->for($ownerCategory)->create([
        'type' => CategoryType::EXPENSE,
        'planned_amount' => 500_000,
    ]);

    $intruderCategory = Category::factory()->for($intruder)->create(['name' => 'Intruder Food', 'type' => CategoryType::EXPENSE]);
    $intruderBudget = Budget::factory()->for($intruder)->create([
        'period_start' => now()->startOfMonth(),
        'period_end' => now()->endOfMonth(),
        'cutoff_day' => 25,
        'is_active' => true,
    ]);
    BudgetItem::factory()->for($intruderBudget)->for($intruderCategory)->create([
        'type' => CategoryType::EXPENSE,
        'planned_amount' => 900_000,
    ]);

    $token = apiToken($owner, 'budgets:read');

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/budgets')
        ->assertOk()
        ->assertJsonCount(1)
        ->assertJsonPath('0.period_start', $ownerBudget->period_start->toDateString())
        ->assertJsonPath('0.cutoff_day', 25)
        ->assertJsonPath('0.is_active', true)
        ->assertJsonPath('0.items.0.category_id', $ownerCategory->id)
        ->assertJsonPath('0.items.0.planned_amount', 500_000)
        ->assertJsonPath('0.items.0.category.id', $ownerCategory->id)
        ->assertJsonPath('0.items.0.category.name', 'Owner Food')
        ->assertJsonMissing(['id' => $intruderBudget->id]);
});

test('budget items include spent-to-date within the budget cycle', function () {
    $user = User::factory()->create();
    $category = Category::factory()->for($user)->create(['name' => 'Food', 'type' => CategoryType::EXPENSE]);
    $balance = Balance::factory()->for($user)->create(['initial_amount' => 0, 'final_amount' => 0]);
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

    Transaction::factory()->for($user)->for($balance)->for($budget)->for($item, 'budgetItem')->for($category)->create([
        'type' => CategoryType::EXPENSE,
        'amount' => 125_000,
        'date' => now(),
    ]);

    $token = apiToken($user, 'budgets:read');

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/budgets')
        ->assertOk()
        ->assertJsonPath('0.items.0.planned_amount', 500_000)
        ->assertJsonPath('0.items.0.actual_amount', 125_000)
        ->assertJsonPath('0.items.0.diff_amount', 375_000);
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
