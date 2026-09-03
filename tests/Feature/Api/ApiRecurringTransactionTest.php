<?php

use App\Enums\CategoryType;
use App\Models\Balance;
use App\Models\Category;
use App\Models\RecurringTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function recurringApiToken(User $user, string $abilities): string
{
    return $user->createToken('test', explode(',', $abilities))->plainTextToken;
}

function recurringPayload(User $user, Balance $balance, ?Category $category = null): array
{
    return [
        'type' => CategoryType::EXPENSE->value,
        'balance_id' => $balance->id,
        'category_id' => $category?->id,
        'amount' => 150_000,
        'description' => 'gym membership',
        'frequency' => 'monthly',
        'start_date' => now()->startOfMonth()->toDateString(),
        'next_run_date' => now()->addDay()->toDateString(),
    ];
}

// ---------------------------------------------------------------------------
// Unauthenticated coverage
// ---------------------------------------------------------------------------

test('unauthenticated requests to every recurring api endpoint return 401', function () {
    $this->getJson('/api/recurring-transactions')->assertUnauthorized();
    $this->getJson('/api/recurring-transactions/1')->assertUnauthorized();
    $this->postJson('/api/recurring-transactions', [])->assertUnauthorized();
    $this->patchJson('/api/recurring-transactions/1', [])->assertUnauthorized();
    $this->deleteJson('/api/recurring-transactions/1')->assertUnauthorized();
});

// ---------------------------------------------------------------------------
// Store
// ---------------------------------------------------------------------------

test('creating a recurring transaction via the api returns 201 with its relations', function () {
    $user = User::factory()->create();
    $balance = Balance::factory()->for($user)->create(['initial_amount' => 0, 'final_amount' => 0]);
    $category = Category::factory()->for($user)->create(['name' => 'Health', 'type' => CategoryType::EXPENSE]);

    $token = recurringApiToken($user, 'recurring_transactions:write');

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/recurring-transactions', recurringPayload($user, $balance, $category))
        ->assertCreated()
        ->assertJsonPath('type', CategoryType::EXPENSE->value)
        ->assertJsonPath('balance_id', $balance->id)
        ->assertJsonPath('category_id', $category->id)
        ->assertJsonPath('amount', 150_000)
        ->assertJsonPath('description', 'gym membership')
        ->assertJsonPath('frequency', 'monthly')
        ->assertJsonPath('is_active', true)
        ->assertJsonPath('balance.id', $balance->id)
        ->assertJsonPath('category.id', $category->id);

    $this->assertDatabaseHas('recurring_transactions', [
        'user_id' => $user->id,
        'balance_id' => $balance->id,
        'category_id' => $category->id,
        'amount' => 150_000,
        'description' => 'gym membership',
        'frequency' => 'monthly',
    ]);
});

test('creating a recurring transaction validates scoped foreign keys', function () {
    $user = User::factory()->create();
    $intruder = User::factory()->create();
    $balance = Balance::factory()->for($user)->create(['initial_amount' => 0, 'final_amount' => 0]);
    $intruderBalance = Balance::factory()->for($intruder)->create(['initial_amount' => 0, 'final_amount' => 0]);
    $intruderCategory = Category::factory()->for($intruder)->create(['name' => 'Foreign', 'type' => CategoryType::EXPENSE]);

    $token = recurringApiToken($user, 'recurring_transactions:write');

    $payload = recurringPayload($user, $intruderBalance, $intruderCategory);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/recurring-transactions', $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['balance_id', 'category_id']);
});

// ---------------------------------------------------------------------------
// Index
// ---------------------------------------------------------------------------

test('a token only sees its own recurring transactions with relations', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();

    $ownerBalance = Balance::factory()->for($owner)->create(['initial_amount' => 0, 'final_amount' => 0]);
    $ownerCategory = Category::factory()->for($owner)->create(['name' => 'Health', 'type' => CategoryType::EXPENSE]);
    RecurringTransaction::factory()->for($owner)->for($ownerBalance)->for($ownerCategory)->create([
        'description' => 'owner-schedule',
    ]);

    $intruderBalance = Balance::factory()->for($intruder)->create(['initial_amount' => 0, 'final_amount' => 0]);
    RecurringTransaction::factory()->for($intruder)->for($intruderBalance)->create([
        'description' => 'intruder-schedule',
    ]);

    $token = recurringApiToken($owner, 'recurring_transactions:read');

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/recurring-transactions')
        ->assertOk()
        ->assertJsonCount(1)
        ->assertJsonPath('0.description', 'owner-schedule')
        ->assertJsonPath('0.balance.name', $ownerBalance->name)
        ->assertJsonPath('0.category.name', 'Health')
        ->assertJsonMissing(['description' => 'intruder-schedule']);
});

test('recurring index supports is_paginate=true', function () {
    $user = User::factory()->create();
    $balance = Balance::factory()->for($user)->create(['initial_amount' => 0, 'final_amount' => 0]);

    RecurringTransaction::factory()->count(12)->for($user)->for($balance)->create();

    $token = recurringApiToken($user, 'recurring_transactions:read');

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/recurring-transactions?is_paginate=true&per_page=5')
        ->assertOk()
        ->assertJsonStructure([
            'data',
            'links',
            'meta' => ['current_page', 'last_page', 'per_page', 'total'],
        ])
        ->assertJsonCount(5, 'data');
});

// ---------------------------------------------------------------------------
// Show
// ---------------------------------------------------------------------------

test('showing a recurring transaction returns it with relations', function () {
    $user = User::factory()->create();
    $balance = Balance::factory()->for($user)->create(['initial_amount' => 0, 'final_amount' => 0]);
    $category = Category::factory()->for($user)->create(['name' => 'Health', 'type' => CategoryType::EXPENSE]);
    $recurring = RecurringTransaction::factory()->for($user)->for($balance)->for($category)->create([
        'description' => 'yoga-class',
    ]);

    $token = recurringApiToken($user, 'recurring_transactions:read');

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson("/api/recurring-transactions/{$recurring->id}")
        ->assertOk()
        ->assertJsonPath('id', $recurring->id)
        ->assertJsonPath('description', 'yoga-class')
        ->assertJsonPath('balance.id', $balance->id)
        ->assertJsonPath('category.id', $category->id);
});

test('a recurring transaction is scoped to its user in show, update, and destroy', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $balance = Balance::factory()->for($owner)->create(['initial_amount' => 0, 'final_amount' => 0]);
    $recurring = RecurringTransaction::factory()->for($owner)->for($balance)->create([
        'description' => 'owner-schedule',
    ]);

    // Intruder needs a valid own balance so the scoped-exists validation
    // passes — the controller's findOrFail must be what 404s, not a 422.
    $intruderBalance = Balance::factory()->for($intruder)->create(['initial_amount' => 0, 'final_amount' => 0]);

    // Owner can see and mutate it.
    $token = recurringApiToken($owner, 'recurring_transactions:read,recurring_transactions:write');

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson("/api/recurring-transactions/{$recurring->id}")
        ->assertOk()
        ->assertJsonPath('description', 'owner-schedule');

    // Intruder gets 404 on every mutation — never 403, never a foreign row.
    Sanctum::actingAs($intruder, ['recurring_transactions:read']);
    $this->getJson("/api/recurring-transactions/{$recurring->id}")->assertNotFound();

    Sanctum::actingAs($intruder, ['recurring_transactions:write']);
    $this->patchJson("/api/recurring-transactions/{$recurring->id}", recurringPayload($intruder, $intruderBalance))
        ->assertNotFound();
    $this->deleteJson("/api/recurring-transactions/{$recurring->id}")->assertNotFound();

    expect(RecurringTransaction::find($recurring->id))->not->toBeNull();
});

// ---------------------------------------------------------------------------
// Update
// ---------------------------------------------------------------------------

test('updating a recurring transaction merges the payload and returns the row', function () {
    $user = User::factory()->create();
    $balance = Balance::factory()->for($user)->create(['initial_amount' => 0, 'final_amount' => 0]);
    $category = Category::factory()->for($user)->create(['name' => 'Health', 'type' => CategoryType::EXPENSE]);
    $recurring = RecurringTransaction::factory()->for($user)->for($balance)->for($category)->create([
        'description' => 'old plan',
        'amount' => 100_000,
        'frequency' => 'monthly',
    ]);

    $token = recurringApiToken($user, 'recurring_transactions:write');

    $this->withHeader('Authorization', "Bearer {$token}")
        ->patchJson("/api/recurring-transactions/{$recurring->id}", [
            'type' => CategoryType::EXPENSE->value,
            'balance_id' => $balance->id,
            'category_id' => null,
            'amount' => 250_000,
            'description' => 'new plan',
            'frequency' => 'weekly',
            'start_date' => now()->startOfMonth()->toDateString(),
            'next_run_date' => now()->addWeek()->toDateString(),
        ])
        ->assertOk()
        ->assertJsonPath('id', $recurring->id)
        ->assertJsonPath('amount', 250_000)
        ->assertJsonPath('description', 'new plan')
        ->assertJsonPath('frequency', 'weekly')
        ->assertJsonPath('category_id', null);

    $this->assertDatabaseHas('recurring_transactions', [
        'id' => $recurring->id,
        'amount' => 250_000,
        'description' => 'new plan',
        'frequency' => 'weekly',
        'category_id' => null,
    ]);
});

// ---------------------------------------------------------------------------
// Destroy
// ---------------------------------------------------------------------------

test('deleting a recurring transaction returns 204 and removes it', function () {
    $user = User::factory()->create();
    $balance = Balance::factory()->for($user)->create(['initial_amount' => 0, 'final_amount' => 0]);
    $recurring = RecurringTransaction::factory()->for($user)->for($balance)->create();

    $token = recurringApiToken($user, 'recurring_transactions:write');

    $this->withHeader('Authorization', "Bearer {$token}")
        ->deleteJson("/api/recurring-transactions/{$recurring->id}")
        ->assertNoContent();

    expect(RecurringTransaction::find($recurring->id))->toBeNull();
});

// ---------------------------------------------------------------------------
// Ability enforcement
// ---------------------------------------------------------------------------

test('recurring endpoints enforce read vs write abilities', function () {
    $user = User::factory()->create();
    $balance = Balance::factory()->for($user)->create(['initial_amount' => 0, 'final_amount' => 0]);
    $recurring = RecurringTransaction::factory()->for($user)->for($balance)->create();

    Sanctum::actingAs($user, ['recurring_transactions:read']);
    $this->postJson('/api/recurring-transactions', recurringPayload($user, $balance))->assertForbidden();
    $this->patchJson("/api/recurring-transactions/{$recurring->id}", recurringPayload($user, $balance))->assertForbidden();
    $this->deleteJson("/api/recurring-transactions/{$recurring->id}")->assertForbidden();

    Sanctum::actingAs($user, ['recurring_transactions:write']);
    $this->getJson('/api/recurring-transactions')->assertForbidden();
    $this->getJson("/api/recurring-transactions/{$recurring->id}")->assertForbidden();
});
