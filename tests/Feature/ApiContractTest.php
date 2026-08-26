<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function contractToken(User $user, string $abilities): string
{
    return $user->createToken('test', explode(',', $abilities))->plainTextToken;
}

test('unauthenticated request to the api contract returns 401', function () {
    $this->getJson('/api/contract')->assertUnauthorized();
});

test('the contract lists all api routes grouped by feature for a full token', function () {
    $user = User::factory()->create();
    $token = contractToken(
        $user,
        'transactions:read,transactions:write,categories:read,categories:write,balances:read,balances:write,budgets:read,budgets:write,funds:read,funds:write,recurring_transactions:read,recurring_transactions:write',
    );

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/contract')
        ->assertOk()
        ->assertJsonStructure([
            'transactions' => [['method', 'uri', 'ability', 'description', 'request_example', 'response_example']],
            'categories' => [['method', 'uri', 'ability', 'description', 'request_example', 'response_example']],
            'balances' => [['method', 'uri', 'ability', 'description', 'request_example', 'response_example']],
            'budgets' => [['method', 'uri', 'ability', 'description', 'request_example', 'response_example']],
            'funds' => [['method', 'uri', 'ability', 'description', 'request_example', 'response_example']],
            'recurring_transactions' => [['method', 'uri', 'ability', 'description', 'request_example', 'response_example']],
        ]);
});

test('the contract lists exact routes and abilities per feature', function () {
    $user = User::factory()->create();
    $token = contractToken($user, 'transactions:read,transactions:write');

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/contract')
        ->assertOk()
        ->assertJsonCount(4, 'transactions')
        ->assertJsonPath('transactions.0.method', 'GET')
        ->assertJsonPath('transactions.0.uri', '/api/transactions')
        ->assertJsonPath('transactions.0.ability', 'transactions:read')
        ->assertJsonPath('transactions.0.description', 'List transactions')
        ->assertJsonPath('transactions.1.method', 'POST')
        ->assertJsonPath('transactions.1.uri', '/api/transactions')
        ->assertJsonPath('transactions.1.ability', 'transactions:write')
        ->assertJsonPath('transactions.1.description', 'Create a transaction')
        ->assertJsonPath('transactions.2.method', 'PATCH')
        ->assertJsonPath('transactions.2.ability', 'transactions:write')
        ->assertJsonPath('transactions.3.method', 'DELETE')
        ->assertJsonPath('transactions.3.ability', 'transactions:write');
});

test('the contract filters routes by the token abilities', function () {
    $user = User::factory()->create();

    // A read-only token must only see read routes — no write routes it would 403 on.
    // Sanctum::actingAs per phase: Laravel's RequestGuard caches the first
    // authenticated user on the guard for the whole test, so switching to a
    // second Bearer token in the same test would keep resolving the first.
    Sanctum::actingAs($user, ['transactions:read']);
    $this->getJson('/api/contract')
        ->assertOk()
        ->assertJsonCount(1, 'transactions')
        ->assertJsonPath('transactions.0.ability', 'transactions:read')
        ->assertJsonMissing(['categories' => []]);

    // A write-only token sees the write routes but no read routes.
    Sanctum::actingAs($user, ['transactions:write']);
    $this->getJson('/api/contract')
        ->assertOk()
        ->assertJsonCount(3, 'transactions')
        ->assertJsonPath('transactions.0.ability', 'transactions:write');
});

test('the contract lists the recurring transactions routes for a token with that ability', function () {
    $user = User::factory()->create();

    Sanctum::actingAs($user, ['recurring_transactions:read', 'recurring_transactions:write']);
    $this->getJson('/api/contract')
        ->assertOk()
        ->assertJsonCount(5, 'recurring_transactions')
        ->assertJsonPath('recurring_transactions.0.method', 'GET')
        ->assertJsonPath('recurring_transactions.0.uri', '/api/recurring-transactions')
        ->assertJsonPath('recurring_transactions.0.ability', 'recurring_transactions:read')
        ->assertJsonPath('recurring_transactions.1.method', 'GET')
        ->assertJsonPath('recurring_transactions.1.uri', '/api/recurring-transactions/{recurring_transaction}')
        ->assertJsonPath('recurring_transactions.1.ability', 'recurring_transactions:read')
        ->assertJsonPath('recurring_transactions.2.method', 'POST')
        ->assertJsonPath('recurring_transactions.2.ability', 'recurring_transactions:write')
        ->assertJsonPath('recurring_transactions.4.method', 'DELETE')
        ->assertJsonPath('recurring_transactions.4.ability', 'recurring_transactions:write');

    // A token without the recurring ability sees no recurring routes at all.
    Sanctum::actingAs($user, ['transactions:read']);
    $this->getJson('/api/contract')
        ->assertOk()
        ->assertJsonCount(1, 'transactions')
        ->assertJsonMissing(['recurring_transactions' => []]);
});

test('the contract includes request and response examples for every route', function () {
    $user = User::factory()->create();
    $token = contractToken(
        $user,
        'transactions:read,transactions:write,categories:read,categories:write,balances:read,balances:write,budgets:read,budgets:write,funds:read,funds:write,recurring_transactions:read,recurring_transactions:write',
    );

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/contract')
        ->assertOk();

    $data = $response->json();

    // Every route in every feature group has request_example + response_example keys.
    foreach ($data as $feature => $routes) {
        foreach ($routes as $route) {
            expect($route)->toHaveKeys(['request_example', 'response_example'], "Route {$route['method']} {$route['uri']} missing example keys");
        }
    }

    // GET routes have null request_example; POST/PATCH routes have array examples.
    $transactions = collect($data['transactions']);
    expect($transactions->firstWhere('method', 'GET')['request_example'])->toBeNull()
        ->and($transactions->firstWhere('method', 'POST')['request_example'])->toBeArray()
        ->and($transactions->firstWhere('method', 'POST')['request_example'])->toHaveKey('balance_id');

    // Transfer endpoint has from_account_id / to_account_id in its request example.
    $balances = collect($data['balances']);
    $transfer = $balances->firstWhere('uri', '/api/balances/transfer');
    expect($transfer)->not->toBeNull()
        ->and($transfer['request_example'])->toHaveKeys(['from_account_id', 'to_account_id', 'amount'])
        ->and($transfer['response_example'])->toHaveKey('message');

    // DELETE routes have null response_example (204 no content).
    expect($transactions->firstWhere('method', 'DELETE')['response_example'])->toBeNull();
});

test('a token with only funds ability sees funds routes and nothing else', function () {
    $user = User::factory()->create();
    $token = contractToken($user, 'funds:read,funds:write');

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/contract')
        ->assertOk()
        ->assertJsonCount(8, 'funds')
        ->assertJsonPath('funds.0.uri', '/api/funds/upcoming')
        ->assertJsonPath('funds.0.ability', 'funds:read')
        ->assertJsonMissing(['transactions' => []])
        ->assertJsonMissing(['categories' => []])
        ->assertJsonMissing(['budgets' => []])
        ->assertJsonMissing(['recurring_transactions' => []]);
});
