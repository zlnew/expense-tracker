<?php

use App\Enums\CategoryType;
use App\Models\Balance;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function transferToken(User $user, string $abilities): string
{
    return $user->createToken('test', explode(',', $abilities))->plainTextToken;
}

// ---------------------------------------------------------------------------
// Happy path
// ---------------------------------------------------------------------------

test('transferring between accounts debits source, credits destination, and creates paired transactions', function () {
    $user = User::factory()->create();
    $source = Balance::factory()->for($user)->create([
        'name' => 'Cash',
        'initial_amount' => 100_000,
        'final_amount' => 100_000,
    ]);
    $destination = Balance::factory()->for($user)->create([
        'name' => 'Savings',
        'initial_amount' => 50_000,
        'final_amount' => 50_000,
    ]);

    $token = transferToken($user, 'balances:write');

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/balances/transfer', [
            'from_account_id' => $source->id,
            'to_account_id' => $destination->id,
            'date' => '2026-08-17',
            'amount' => 25_000,
            'description' => 'Cash to savings',
        ])
        ->assertOk()
        ->assertJsonPath('message', 'Transfer completed');

    expect($source->fresh()->final_amount)->toBe(75_000)
        ->and($destination->fresh()->final_amount)->toBe(75_000);

    // Two transactions with a matching transfer_group_id.
    $transactions = Transaction::query()
        ->where('transfer_group_id', '!=', null)
        ->orderBy('id')
        ->get();

    expect($transactions)->toHaveCount(2)
        ->and($transactions[0]->transfer_group_id)->toBe($transactions[1]->transfer_group_id)
        ->and($transactions[0]->balance_id)->toBe($source->id)
        ->and($transactions[0]->type)->toBe(CategoryType::EXPENSE)
        ->and($transactions[0]->amount)->toBe(25_000)
        ->and($transactions[0]->description)->toBe('Cash to savings')
        ->and($transactions[1]->balance_id)->toBe($destination->id)
        ->and($transactions[1]->type)->toBe(CategoryType::INCOME)
        ->and($transactions[1]->amount)->toBe(25_000);
});

// ---------------------------------------------------------------------------
// Ability enforcement
// ---------------------------------------------------------------------------

test('transferring requires the balances:write ability', function () {
    $user = User::factory()->create();
    $source = Balance::factory()->for($user)->create(['initial_amount' => 100_000, 'final_amount' => 100_000]);
    $destination = Balance::factory()->for($user)->create(['initial_amount' => 0, 'final_amount' => 0]);

    Sanctum::actingAs($user, ['balances:read']);
    $this->postJson('/api/balances/transfer', [
        'from_account_id' => $source->id,
        'to_account_id' => $destination->id,
        'date' => '2026-08-17',
        'amount' => 10_000,
    ])->assertForbidden();
});

test('unauthenticated transfer returns 401', function () {
    $this->postJson('/api/balances/transfer', [])->assertUnauthorized();
});

// ---------------------------------------------------------------------------
// User scoping
// ---------------------------------------------------------------------------

test('transferring cannot use another user\'s balance', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $ownerBalance = Balance::factory()->for($owner)->create(['initial_amount' => 100_000, 'final_amount' => 100_000]);
    $intruderBalance = Balance::factory()->for($intruder)->create(['initial_amount' => 50_000, 'final_amount' => 50_000]);

    $token = transferToken($intruder, 'balances:write');

    // from_account_id belongs to another user
    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/balances/transfer', [
            'from_account_id' => $ownerBalance->id,
            'to_account_id' => $intruderBalance->id,
            'date' => '2026-08-17',
            'amount' => 10_000,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('from_account_id');

    // to_account_id belongs to another user
    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/balances/transfer', [
            'from_account_id' => $intruderBalance->id,
            'to_account_id' => $ownerBalance->id,
            'date' => '2026-08-17',
            'amount' => 10_000,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('to_account_id');
});

// ---------------------------------------------------------------------------
// Validation
// ---------------------------------------------------------------------------

test('transferring validates required fields', function () {
    $user = User::factory()->create();

    $token = transferToken($user, 'balances:write');

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/balances/transfer', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['from_account_id', 'to_account_id', 'date', 'amount']);
});

test('transferring rejects zero and negative amounts', function () {
    $user = User::factory()->create();
    $source = Balance::factory()->for($user)->create(['initial_amount' => 100_000, 'final_amount' => 100_000]);
    $destination = Balance::factory()->for($user)->create(['initial_amount' => 0, 'final_amount' => 0]);

    $token = transferToken($user, 'balances:write');

    foreach ([0, -100] as $amount) {
        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/balances/transfer', [
                'from_account_id' => $source->id,
                'to_account_id' => $destination->id,
                'date' => '2026-08-17',
                'amount' => $amount,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('amount');
    }
});

test('transferring between the same account is rejected', function () {
    $user = User::factory()->create();
    $balance = Balance::factory()->for($user)->create(['initial_amount' => 100_000, 'final_amount' => 100_000]);

    $token = transferToken($user, 'balances:write');

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/balances/transfer', [
            'from_account_id' => $balance->id,
            'to_account_id' => $balance->id,
            'date' => '2026-08-17',
            'amount' => 10_000,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('account');
});

test('transferring more than the available balance is rejected', function () {
    $user = User::factory()->create();
    $source = Balance::factory()->for($user)->create(['initial_amount' => 10_000, 'final_amount' => 10_000]);
    $destination = Balance::factory()->for($user)->create(['initial_amount' => 0, 'final_amount' => 0]);

    $token = transferToken($user, 'balances:write');

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/balances/transfer', [
            'from_account_id' => $source->id,
            'to_account_id' => $destination->id,
            'date' => '2026-08-17',
            'amount' => 50_000,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('account');

    // Balances unchanged.
    expect($source->fresh()->final_amount)->toBe(10_000)
        ->and($destination->fresh()->final_amount)->toBe(0);
});
