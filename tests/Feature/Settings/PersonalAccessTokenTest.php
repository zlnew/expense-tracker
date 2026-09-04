<?php

use App\Models\Balance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create([
        'name' => 'Maulana',
        'email' => 'maul@example.com',
    ]);

    $this->balance = Balance::factory()->create([
        'user_id' => $this->user->id,
        'name' => 'Cash Wallet',
        'initial_amount' => 500000,
        'final_amount' => 500000,
        'is_primary' => true,
    ]);
});

test('personal access token endpoints require authentication and redirect guest users', function () {
    $this->get('/settings/personal-access-tokens')->assertRedirect('/login');
    $this->post('/settings/personal-access-tokens', ['name' => 'Antigravity'])->assertRedirect('/login');
    $this->delete('/settings/personal-access-tokens/1')->assertRedirect('/login');
});

test('user can list only their own personal access tokens', function () {
    $otherUser = User::factory()->create();

    $this->user->createToken('My Test Token 1', ['*']);
    $this->user->createToken('My Test Token 2', ['transactions:read']);
    $otherUser->createToken('Other Token', ['*']);

    $response = $this->actingAs($this->user)
        ->getJson('/settings/personal-access-tokens');

    $response->assertOk()
        ->assertJsonCount(2);

    $names = collect($response->json())->pluck('name')->all();
    expect($names)->toContain('My Test Token 1')
        ->toContain('My Test Token 2')
        ->not->toContain('Other Token');
});

test('user can create a personal access token and receive plaintext token', function () {
    $response = $this->actingAs($this->user)
        ->postJson('/settings/personal-access-tokens', [
            'name' => 'Antigravity MCP Agent',
            'abilities' => ['transactions:read', 'transactions:write', 'balances:read'],
        ]);

    $response->assertCreated()
        ->assertJsonStructure([
            'token' => ['id', 'name', 'abilities', 'created_at'],
            'plainTextToken',
        ])
        ->assertJsonPath('token.name', 'Antigravity MCP Agent')
        ->assertJsonPath('token.abilities', ['transactions:read', 'transactions:write', 'balances:read']);

    $plainTextToken = $response->json('plainTextToken');
    expect($plainTextToken)->not->toBeEmpty();

    // Verify token exists in database
    $tokenId = $response->json('token.id');
    $dbToken = PersonalAccessToken::find($tokenId);
    expect($dbToken)->not->toBeNull()
        ->and($dbToken->tokenable_id)->toBe($this->user->id);

    // Logout session user so next request tests authenticating with the Bearer token directly
    auth('web')->logout();
    $this->app['auth']->forgetGuards();
    $this->flushHeaders();

    // Verify the newly created token can authenticate /api/mcp
    $mcpResponse = $this->withHeader('Authorization', 'Bearer '.$plainTextToken)
        ->postJson('/api/mcp', [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'tools/list',
        ]);

    $mcpResponse->assertOk()
        ->assertJsonPath('result.tools.0.name', 'list_transactions');
});

test('user can create token with full access when abilities are omitted', function () {
    $response = $this->actingAs($this->user)
        ->postJson('/settings/personal-access-tokens', [
            'name' => 'Full Access Token',
        ]);

    $response->assertCreated()
        ->assertJsonPath('token.abilities', ['*']);

    $plainTextToken = $response->json('plainTextToken');

    // Logout session user so Bearer token is tested
    auth('web')->logout();
    $this->app['auth']->forgetGuards();
    $this->flushHeaders();

    // Works for MCP
    $this->withHeader('Authorization', 'Bearer '.$plainTextToken)
        ->postJson('/api/mcp', [
            'jsonrpc' => '2.0',
            'id' => 2,
            'method' => 'ping',
        ])->assertOk();
});

test('user can revoke a personal access token and access is immediately revoked', function () {
    $tokenResult = $this->user->createToken('Temporary Token', ['*']);
    $plainText = $tokenResult->plainTextToken;
    $tokenId = $tokenResult->accessToken->id;

    // 1. Token works
    $this->withHeader('Authorization', 'Bearer '.$plainText)
        ->postJson('/api/mcp', [
            'jsonrpc' => '2.0',
            'id' => 3,
            'method' => 'ping',
        ])->assertOk();

    // 2. Revoke token via settings endpoint
    $delResponse = $this->actingAs($this->user)
        ->deleteJson("/settings/personal-access-tokens/{$tokenId}");

    $delResponse->assertOk()
        ->assertJson(['success' => true]);

    expect(PersonalAccessToken::find($tokenId))->toBeNull();

    // 3. Clear session so request relies solely on the Bearer header
    auth('web')->logout();
    $this->app['auth']->forgetGuards();
    $this->flushHeaders();

    // Token no longer works -> 401 Unauthorized
    $this->withHeader('Authorization', 'Bearer '.$plainText)
        ->postJson('/api/mcp', [
            'jsonrpc' => '2.0',
            'id' => 4,
            'method' => 'ping',
        ])->assertUnauthorized();
});

test('user cannot revoke another users personal access token', function () {
    $intruder = User::factory()->create();
    $tokenResult = $intruder->createToken('Intruder Token', ['*']);
    $intruderTokenId = $tokenResult->accessToken->id;

    // Maulana attempts to delete intruder's token -> 404
    $response = $this->actingAs($this->user)
        ->deleteJson("/settings/personal-access-tokens/{$intruderTokenId}");

    $response->assertNotFound();

    // Token was not deleted
    expect(PersonalAccessToken::find($intruderTokenId))->not->toBeNull();
});
