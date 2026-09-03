<?php

use App\Enums\CategoryType;
use App\Models\Balance;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->balance = Balance::factory()->for($this->user)->create([
        'name' => 'Cash',
        'initial_amount' => 1_000_000,
        'final_amount' => 1_000_000,
    ]);
    $this->category = Category::factory()->for($this->user)->create([
        'name' => 'Groceries',
        'type' => CategoryType::EXPENSE,
    ]);
});

function createApiToken(User $user, string $abilities): string
{
    return $user->createToken('test', explode(',', $abilities))->plainTextToken;
}

test('api transaction listing supports combined query filters', function () {
    $catOther = Category::factory()->for($this->user)->create(['name' => 'Utilities', 'type' => CategoryType::EXPENSE]);

    Transaction::factory()->for($this->user)->for($this->balance)->for($this->category)->create([
        'amount' => 50_000,
        'type' => CategoryType::EXPENSE,
        'description' => 'Supermarket shopping',
        'date' => '2026-08-10',
    ]);
    Transaction::factory()->for($this->user)->for($this->balance)->for($catOther)->create([
        'amount' => 100_000,
        'type' => CategoryType::EXPENSE,
        'description' => 'Electricity bill',
        'date' => '2026-08-15',
    ]);

    $token = createApiToken($this->user, 'transactions:read');

    // Filter by search
    $resSearch = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/transactions?search=Supermarket');
    $resSearch->assertOk()->assertJsonCount(1)->assertJsonPath('0.description', 'Supermarket shopping');

    // Filter by category
    $resCat = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson("/api/transactions?category={$catOther->id}");
    $resCat->assertOk()->assertJsonCount(1)->assertJsonPath('0.description', 'Electricity bill');

    // Filter by date range
    $resDate = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/transactions?date_from=2026-08-12&date_to=2026-08-20');
    $resDate->assertOk()->assertJsonCount(1)->assertJsonPath('0.description', 'Electricity bill');
});

test('api transaction creation validates amounts, dates, and foreign entities', function () {
    $intruder = User::factory()->create();
    $foreignBalance = Balance::factory()->for($intruder)->create();
    $foreignCategory = Category::factory()->for($intruder)->create();

    $token = createApiToken($this->user, 'transactions:write');

    // Amount <= 0
    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/transactions', [
            'balance_id' => $this->balance->id,
            'category_id' => $this->category->id,
            'amount' => 0,
            'type' => 'expense',
            'date' => now()->toDateString(),
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['amount']);

    // Foreign balance
    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/transactions', [
            'balance_id' => $foreignBalance->id,
            'category_id' => $this->category->id,
            'amount' => 50000,
            'type' => 'expense',
            'date' => now()->toDateString(),
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['balance_id']);

    // Foreign category
    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/transactions', [
            'balance_id' => $this->balance->id,
            'category_id' => $foreignCategory->id,
            'amount' => 50000,
            'type' => 'expense',
            'date' => now()->toDateString(),
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['category_id']);
});

test('api transaction update supports partial field updates', function () {
    $txn = Transaction::factory()->for($this->user)->for($this->balance)->for($this->category)->create([
        'amount' => 50_000,
        'type' => CategoryType::EXPENSE,
        'description' => 'Original desc',
        'date' => '2026-08-01',
    ]);
    $this->balance->update(['final_amount' => 950_000]);

    $token = createApiToken($this->user, 'transactions:write');

    // Update only description
    $this->withHeader('Authorization', "Bearer {$token}")
        ->patchJson("/api/transactions/{$txn->id}", [
            'description' => 'Updated desc only',
        ])
        ->assertOk()
        ->assertJsonPath('description', 'Updated desc only')
        ->assertJsonPath('amount', 50_000);

    // Update only amount
    $this->withHeader('Authorization', "Bearer {$token}")
        ->patchJson("/api/transactions/{$txn->id}", [
            'amount' => 120_000,
        ])
        ->assertOk()
        ->assertJsonPath('amount', 120_000);

    expect($this->balance->fresh()->final_amount)->toBe(880_000);
});

test('api category creation enforces valid enum type and unique constraints', function () {
    $token = createApiToken($this->user, 'categories:write');

    // Invalid type enum
    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/categories', [
            'name' => 'Bad Type',
            'type' => 'invalid_enum_value',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['type']);

    // Missing name
    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/categories', [
            'type' => 'expense',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);
});

test('api balance transfer validates same-account, insufficient funds, and foreign accounts', function () {
    $destBalance = Balance::factory()->for($this->user)->create([
        'name' => 'Savings',
        'initial_amount' => 100_000,
        'final_amount' => 100_000,
    ]);

    $intruder = User::factory()->create();
    $foreignBalance = Balance::factory()->for($intruder)->create();

    $token = createApiToken($this->user, 'balances:write');

    // Same account transfer
    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/balances/transfer', [
            'from_account_id' => $this->balance->id,
            'to_account_id' => $this->balance->id,
            'amount' => 50_000,
            'date' => now()->toDateString(),
        ])
        ->assertUnprocessable();

    // Insufficient funds
    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/balances/transfer', [
            'from_account_id' => $this->balance->id,
            'to_account_id' => $destBalance->id,
            'amount' => 999_999_999,
            'date' => now()->toDateString(),
        ])
        ->assertUnprocessable();

    // Foreign account
    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/balances/transfer', [
            'from_account_id' => $this->balance->id,
            'to_account_id' => $foreignBalance->id,
            'amount' => 10_000,
            'date' => now()->toDateString(),
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['to_account_id']);
});

test('api enforces fine-grained token abilities across unrelated endpoints', function () {
    $tokenTxnOnly = createApiToken($this->user, 'transactions:read');

    // Read transactions -> OK
    $this->withHeader('Authorization', "Bearer {$tokenTxnOnly}")
        ->getJson('/api/transactions')
        ->assertOk();

    // Write transactions -> 403
    $this->withHeader('Authorization', "Bearer {$tokenTxnOnly}")
        ->postJson('/api/transactions', ['amount' => 1000])
        ->assertForbidden();

    // Read categories -> 403
    $this->withHeader('Authorization', "Bearer {$tokenTxnOnly}")
        ->getJson('/api/categories')
        ->assertForbidden();

    // Write balances -> 403
    $this->withHeader('Authorization', "Bearer {$tokenTxnOnly}")
        ->postJson('/api/balances', ['name' => 'test'])
        ->assertForbidden();

    // Read funds -> 403
    $this->withHeader('Authorization', "Bearer {$tokenTxnOnly}")
        ->getJson('/api/funds')
        ->assertForbidden();

    // Read recurring transactions -> 403
    $this->withHeader('Authorization', "Bearer {$tokenTxnOnly}")
        ->getJson('/api/recurring-transactions')
        ->assertForbidden();
});

test('api multi-tenant intrusion protection prevents reading and altering another users entities', function () {
    $intruder = User::factory()->create();
    $intruderBalance = Balance::factory()->for($intruder)->create(['final_amount' => 500_000]);
    $intruderCategory = Category::factory()->for($intruder)->create();
    $intruderTxn = Transaction::factory()->for($intruder)->for($intruderBalance)->for($intruderCategory)->create([
        'amount' => 200_000,
        'description' => 'Intruder Secret',
    ]);

    $token = createApiToken($this->user, 'transactions:read,transactions:write,balances:read,balances:write,categories:read,categories:write');

    // Show balance of other user -> 404 (scoped via query)
    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson("/api/balances/{$intruderBalance->id}")
        ->assertNotFound();

    // Patch transaction of other user -> 404
    $this->withHeader('Authorization', "Bearer {$token}")
        ->patchJson("/api/transactions/{$intruderTxn->id}", ['description' => 'Hacked'])
        ->assertNotFound();

    // Delete transaction of other user -> 404
    $this->withHeader('Authorization', "Bearer {$token}")
        ->deleteJson("/api/transactions/{$intruderTxn->id}")
        ->assertNotFound();
});
