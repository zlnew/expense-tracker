<?php

use App\Enums\CategoryType;
use App\Models\Balance;
use App\Models\Budget;
use App\Models\BudgetItem;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function apiCrudToken(User $user, string $abilities): string
{
    return $user->createToken('test', explode(',', $abilities))->plainTextToken;
}

// ---------------------------------------------------------------------------
// Unauthenticated coverage for every new route
// ---------------------------------------------------------------------------

test('unauthenticated requests to every new crud endpoint return 401', function () {
    $this->postJson('/api/balances', [])->assertUnauthorized();
    $this->getJson('/api/balances/1')->assertUnauthorized();
    $this->patchJson('/api/balances/1', [])->assertUnauthorized();
    $this->deleteJson('/api/balances/1')->assertUnauthorized();

    $this->postJson('/api/categories', [])->assertUnauthorized();
    $this->patchJson('/api/categories/1', [])->assertUnauthorized();
    $this->deleteJson('/api/categories/1')->assertUnauthorized();

    $this->postJson('/api/budgets', [])->assertUnauthorized();
    $this->patchJson('/api/budgets/1', [])->assertUnauthorized();
    $this->deleteJson('/api/budgets/1')->assertUnauthorized();
    $this->postJson('/api/budgets/1/set-active')->assertUnauthorized();

    $this->deleteJson('/api/transactions/1')->assertUnauthorized();
});

// ---------------------------------------------------------------------------
// Balances
// ---------------------------------------------------------------------------

test('creating a balance via the api returns 201 and syncs the final amount', function () {
    $user = User::factory()->create();
    $token = apiCrudToken($user, 'balances:write');

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/balances', [
            'name' => 'Cash',
            'description' => 'wallet',
            'initial_amount' => 150_000,
        ])
        ->assertCreated()
        ->assertJsonPath('name', 'Cash')
        ->assertJsonPath('description', 'wallet')
        ->assertJsonPath('initial_amount', 150_000)
        ->assertJsonPath('final_amount', 150_000)
        ->assertJsonPath('user_id', $user->id);

    $this->assertDatabaseHas('balances', [
        'user_id' => $user->id,
        'name' => 'Cash',
        'initial_amount' => 150_000,
        'final_amount' => 150_000,
    ]);
});

test('balance endpoints enforce read vs write abilities', function () {
    $user = User::factory()->create();

    Sanctum::actingAs($user, ['balances:read']);
    $this->postJson('/api/balances', ['name' => 'x', 'initial_amount' => 1])->assertForbidden();
    $this->patchJson('/api/balances/1', ['name' => 'x'])->assertForbidden();
    $this->deleteJson('/api/balances/1')->assertForbidden();

    Sanctum::actingAs($user, ['balances:write']);
    $this->getJson('/api/balances')->assertForbidden();
    $this->getJson('/api/balances/1')->assertForbidden();
});

test('a balance is scoped to its user in show, update, and destroy', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();

    $balance = Balance::factory()->for($owner)->create([
        'name' => 'Owner Cash',
        'initial_amount' => 100_000,
        'final_amount' => 100_000,
    ]);

    $token = apiCrudToken($owner, 'balances:read,balances:write');

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson("/api/balances/{$balance->id}")
        ->assertOk()
        ->assertJsonPath('name', 'Owner Cash');

    // Sanctum::actingAs per request avoids Laravel's RequestGuard user cache
    // leaking the owner identity across requests in one test.
    Sanctum::actingAs($intruder, ['balances:read']);
    $this->getJson("/api/balances/{$balance->id}")->assertNotFound();

    Sanctum::actingAs($intruder, ['balances:write']);
    $this->patchJson("/api/balances/{$balance->id}", [
        'name' => 'hijacked',
        'initial_amount' => 1,
    ])->assertNotFound();
    $this->deleteJson("/api/balances/{$balance->id}")->assertNotFound();
});

test('updating a balance saves the merged payload and resyncs the final amount', function () {
    $user = User::factory()->create();
    $balance = Balance::factory()->for($user)->create([
        'name' => 'Cash',
        'description' => 'old desc',
        'initial_amount' => 100_000,
        'final_amount' => 100_000,
    ]);

    $token = apiCrudToken($user, 'balances:write');

    $this->withHeader('Authorization', "Bearer {$token}")
        ->patchJson("/api/balances/{$balance->id}", [
            'name' => 'Wallet',
            'description' => 'new desc',
            'initial_amount' => 200_000,
        ])
        ->assertOk()
        ->assertJsonPath('name', 'Wallet')
        ->assertJsonPath('description', 'new desc')
        ->assertJsonPath('initial_amount', 200_000)
        ->assertJsonPath('final_amount', 200_000);

    $this->assertDatabaseHas('balances', [
        'id' => $balance->id,
        'name' => 'Wallet',
        'initial_amount' => 200_000,
        'final_amount' => 200_000,
    ]);
});

test('deleting a balance removes it and the primary balance is protected', function () {
    $user = User::factory()->create();
    $primary = Balance::factory()->for($user)->create([
        'name' => 'Primary',
        'initial_amount' => 0,
        'final_amount' => 0,
        'is_primary' => true,
    ]);
    $savings = Balance::factory()->for($user)->create([
        'name' => 'Savings',
        'initial_amount' => 0,
        'final_amount' => 0,
    ]);

    $token = apiCrudToken($user, 'balances:write');

    $this->withHeader('Authorization', "Bearer {$token}")
        ->deleteJson("/api/balances/{$primary->id}")
        ->assertUnprocessable()
        ->assertJsonValidationErrors('balance');

    $this->withHeader('Authorization', "Bearer {$token}")
        ->deleteJson("/api/balances/{$savings->id}")
        ->assertNoContent();

    expect(Balance::find($savings->id))->toBeNull()
        ->and(Balance::find($primary->id))->not->toBeNull();
});

// ---------------------------------------------------------------------------
// Categories
// ---------------------------------------------------------------------------

test('creating a category via the api returns 201', function () {
    $user = User::factory()->create();
    $token = apiCrudToken($user, 'categories:write');

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/categories', [
            'type' => CategoryType::EXPENSE->value,
            'name' => 'Food',
        ])
        ->assertCreated()
        ->assertJsonPath('name', 'Food')
        ->assertJsonPath('type', CategoryType::EXPENSE->value);

    $this->assertDatabaseHas('categories', [
        'user_id' => $user->id,
        'type' => CategoryType::EXPENSE->value,
        'name' => 'Food',
    ]);
});

test('category endpoints enforce read vs write abilities', function () {
    $user = User::factory()->create();

    Sanctum::actingAs($user, ['categories:read']);
    $this->postJson('/api/categories', ['type' => 'expense', 'name' => 'x'])->assertForbidden();
    $this->patchJson('/api/categories/1', ['name' => 'x'])->assertForbidden();
    $this->deleteJson('/api/categories/1')->assertForbidden();

    Sanctum::actingAs($user, ['categories:write']);
    $this->getJson('/api/categories')->assertForbidden();
});

test('a category is scoped to its user and can be updated and destroyed', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();

    $category = Category::factory()->for($owner)->create([
        'name' => 'Food',
        'type' => CategoryType::EXPENSE,
    ]);

    $token = apiCrudToken($owner, 'categories:write');

    $this->withHeader('Authorization', "Bearer {$token}")
        ->patchJson("/api/categories/{$category->id}", [
            'type' => CategoryType::EXPENSE->value,
            'name' => 'Groceries',
        ])
        ->assertOk()
        ->assertJsonPath('name', 'Groceries');

    $this->assertDatabaseHas('categories', ['id' => $category->id, 'name' => 'Groceries']);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->deleteJson("/api/categories/{$category->id}")
        ->assertNoContent();

    expect(Category::find($category->id))->toBeNull();

    $foreign = Category::factory()->for($owner)->create([
        'name' => 'Transport',
        'type' => CategoryType::EXPENSE,
    ]);

    Sanctum::actingAs($intruder, ['categories:write']);
    $this->patchJson("/api/categories/{$foreign->id}", [
        'type' => CategoryType::EXPENSE->value,
        'name' => 'hijacked',
    ])->assertNotFound();
    $this->deleteJson("/api/categories/{$foreign->id}")->assertNotFound();
});

test('deleting a category with related transactions is rejected', function () {
    $user = User::factory()->create();
    $balance = Balance::factory()->for($user)->create(['initial_amount' => 0, 'final_amount' => 0]);
    $category = Category::factory()->for($user)->create([
        'name' => 'Food',
        'type' => CategoryType::EXPENSE,
    ]);
    Transaction::factory()->for($user)->for($balance)->for($category)->create(['amount' => 10_000]);

    $token = apiCrudToken($user, 'categories:write');

    $this->withHeader('Authorization', "Bearer {$token}")
        ->deleteJson("/api/categories/{$category->id}")
        ->assertUnprocessable()
        ->assertJsonValidationErrors('category');

    expect(Category::find($category->id))->not->toBeNull();
});

// ---------------------------------------------------------------------------
// Budgets
// ---------------------------------------------------------------------------

test('creating a budget via the api returns 201 with its items', function () {
    $user = User::factory()->create();
    $category = Category::factory()->for($user)->create([
        'name' => 'Food',
        'type' => CategoryType::EXPENSE,
    ]);

    $token = apiCrudToken($user, 'budgets:write');

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/budgets', [
            'period_start' => now()->startOfMonth()->toDateString(),
            'period_end' => now()->endOfMonth()->toDateString(),
            'cutoff_day' => 25,
            'notes' => 'August plan',
            'items' => [
                ['category_id' => $category->id, 'type' => CategoryType::EXPENSE->value, 'planned_amount' => 500_000],
            ],
        ])
        ->assertCreated()
        ->assertJsonPath('cutoff_day', 25)
        ->assertJsonPath('notes', 'August plan')
        ->assertJsonPath('items.0.category_id', $category->id)
        ->assertJsonPath('items.0.planned_amount', 500_000);

    $this->assertDatabaseHas('budgets', ['user_id' => $user->id, 'cutoff_day' => 25]);
    $this->assertDatabaseHas('budget_items', ['category_id' => $category->id, 'planned_amount' => 500_000]);
});

test('updating a budget without items preserves the existing items', function () {
    $user = User::factory()->create();
    $category = Category::factory()->for($user)->create([
        'name' => 'Food',
        'type' => CategoryType::EXPENSE,
    ]);
    $budget = Budget::factory()->for($user)->create(['cutoff_day' => 25]);
    $item = BudgetItem::factory()->for($budget)->for($category)->create([
        'type' => CategoryType::EXPENSE,
        'planned_amount' => 500_000,
    ]);

    $token = apiCrudToken($user, 'budgets:write');

    // A partial update that omits `items` must not fall into SaveBudget's
    // prune path and silently delete the existing item.
    $this->withHeader('Authorization', "Bearer {$token}")
        ->patchJson("/api/budgets/{$budget->id}", [
            'period_start' => now()->startOfMonth()->toDateString(),
            'period_end' => now()->endOfMonth()->toDateString(),
            'cutoff_day' => 1,
            'notes' => 'updated',
        ])
        ->assertOk()
        ->assertJsonPath('cutoff_day', 1)
        ->assertJsonPath('items.0.id', $item->id)
        ->assertJsonPath('items.0.planned_amount', 500_000);

    $this->assertDatabaseHas('budget_items', ['id' => $item->id, 'planned_amount' => 500_000]);
});

test('setting a budget active deactivates the others', function () {
    $user = User::factory()->create();
    $active = Budget::factory()->for($user)->create(['is_active' => true]);
    $other = Budget::factory()->for($user)->create(['is_active' => false]);

    $token = apiCrudToken($user, 'budgets:write');

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/budgets/{$other->id}/set-active")
        ->assertOk()
        ->assertJsonPath('id', $other->id)
        ->assertJsonPath('is_active', true);

    expect($active->fresh()->is_active)->toBeFalse()
        ->and($other->fresh()->is_active)->toBeTrue();
});

test('budget endpoints enforce read vs write abilities', function () {
    $user = User::factory()->create();

    Sanctum::actingAs($user, ['budgets:read']);
    $this->postJson('/api/budgets', [])->assertForbidden();
    $this->patchJson('/api/budgets/1', [])->assertForbidden();
    $this->deleteJson('/api/budgets/1')->assertForbidden();
    $this->postJson('/api/budgets/1/set-active')->assertForbidden();

    Sanctum::actingAs($user, ['budgets:write']);
    $this->getJson('/api/budgets')->assertForbidden();
});

test('a budget is scoped to its user in update, destroy, and set-active', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $budget = Budget::factory()->for($owner)->create(['is_active' => true]);

    Sanctum::actingAs($intruder, ['budgets:write']);
    $this->patchJson("/api/budgets/{$budget->id}", [
        'period_start' => now()->startOfMonth()->toDateString(),
        'period_end' => now()->endOfMonth()->toDateString(),
        'cutoff_day' => 1,
    ])->assertNotFound();
    $this->deleteJson("/api/budgets/{$budget->id}")->assertNotFound();
    $this->postJson("/api/budgets/{$budget->id}/set-active")->assertNotFound();
});

test('deleting an active budget is rejected and an inactive one is deleted', function () {
    $user = User::factory()->create();
    $active = Budget::factory()->for($user)->create(['is_active' => true]);
    $inactive = Budget::factory()->for($user)->create(['is_active' => false]);

    $token = apiCrudToken($user, 'budgets:write');

    $this->withHeader('Authorization', "Bearer {$token}")
        ->deleteJson("/api/budgets/{$active->id}")
        ->assertUnprocessable()
        ->assertJsonValidationErrors('budget');

    $this->withHeader('Authorization', "Bearer {$token}")
        ->deleteJson("/api/budgets/{$inactive->id}")
        ->assertNoContent();

    expect(Budget::find($inactive->id))->toBeNull();
});

// ---------------------------------------------------------------------------
// Transactions — destroy
// ---------------------------------------------------------------------------

test('deleting a transaction returns 204 and resyncs the balance', function () {
    $user = User::factory()->create();
    $balance = Balance::factory()->for($user)->create(['initial_amount' => 100_000, 'final_amount' => 100_000]);
    $category = Category::factory()->for($user)->create([
        'name' => 'Food',
        'type' => CategoryType::EXPENSE,
    ]);

    $token = apiCrudToken($user, 'transactions:write');

    // Create through the API so the balance syncs to the expense amount.
    $created = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/transactions', [
            'balance_id' => $balance->id,
            'category_id' => $category->id,
            'type' => CategoryType::EXPENSE->value,
            'date' => now()->toDateString(),
            'amount' => 25_000,
        ])
        ->assertCreated();

    $transactionId = $created->json('id');

    expect($balance->fresh()->final_amount)->toBe(75_000);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->deleteJson("/api/transactions/{$transactionId}")
        ->assertNoContent();

    expect(Transaction::find($transactionId))->toBeNull()
        ->and($balance->fresh()->final_amount)->toBe(100_000);
});

test('deleting a transaction requires write ability and is user-scoped', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $balance = Balance::factory()->for($owner)->create(['initial_amount' => 0, 'final_amount' => 0]);
    $transaction = Transaction::factory()->for($owner)->for($balance)->create(['amount' => 10_000]);

    Sanctum::actingAs($owner, ['transactions:read']);
    $this->deleteJson("/api/transactions/{$transaction->id}")->assertForbidden();

    Sanctum::actingAs($intruder, ['transactions:write']);
    $this->deleteJson("/api/transactions/{$transaction->id}")->assertNotFound();

    expect(Transaction::find($transaction->id))->not->toBeNull();
});
