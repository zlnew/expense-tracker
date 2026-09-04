<?php

use App\Mcp\McpServer;
use App\Models\Balance;
use App\Models\Category;
use App\Models\OAuthAuthCode;
use App\Models\OAuthClient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

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

    $this->category = Category::factory()->create([
        'user_id' => $this->user->id,
        'name' => 'Groceries',
        'type' => 'expense',
    ]);

    $this->client = OAuthClient::create([
        'id' => 'gemini-client-123',
        'user_id' => $this->user->id,
        'name' => 'Google Gemini Web',
        'secret' => 'gemini-secret-456',
        'redirect_uri' => 'https://gemini.google.com/auth/callback',
    ]);
});

test('gemini web browser preflight options request succeeds with cors headers on mcp and oauth endpoints', function () {
    $origin = 'https://gemini.google.com';

    // 1. OPTIONS /api/mcp
    $mcpPreflight = $this->withHeaders([
        'Origin' => $origin,
        'Access-Control-Request-Method' => 'POST',
        'Access-Control-Request-Headers' => 'Authorization, Content-Type, Mcp-Session-Id',
    ])->call('OPTIONS', '/api/mcp');

    $mcpPreflight->assertStatus(204)
        ->assertHeader('Access-Control-Allow-Origin', '*')
        ->assertHeader('Access-Control-Allow-Methods', 'GET, POST, OPTIONS');
    expect($mcpPreflight->headers->get('Access-Control-Expose-Headers'))
        ->toContain('Mcp-Session-Id')
        ->toContain('WWW-Authenticate');

    // 2. OPTIONS /oauth/token
    $tokenPreflight = $this->withHeaders([
        'Origin' => $origin,
        'Access-Control-Request-Method' => 'POST',
    ])->call('OPTIONS', '/oauth/token');

    $tokenPreflight->assertStatus(204)
        ->assertHeader('Access-Control-Allow-Origin', '*');

    // 3. OPTIONS /.well-known/oauth-authorization-server
    $asPreflight = $this->withHeaders([
        'Origin' => $origin,
        'Access-Control-Request-Method' => 'GET',
    ])->call('OPTIONS', '/.well-known/oauth-authorization-server');

    $asPreflight->assertStatus(204)
        ->assertHeader('Access-Control-Allow-Origin', '*');

    // 4. OPTIONS /.well-known/oauth-protected-resource
    $prPreflight = $this->withHeaders([
        'Origin' => $origin,
        'Access-Control-Request-Method' => 'GET',
    ])->call('OPTIONS', '/.well-known/oauth-protected-resource');

    $prPreflight->assertStatus(204)
        ->assertHeader('Access-Control-Allow-Origin', '*');
});

test('gemini web browser unauthenticated request receives 401 with cors and www-authenticate header', function () {
    $origin = 'https://gemini.google.com';

    $response = $this->withHeaders([
        'Origin' => $origin,
    ])->postJson('/api/mcp', [
        'jsonrpc' => '2.0',
        'method' => 'tools/list',
        'id' => 1,
    ]);

    $response->assertUnauthorized()
        ->assertHeader('Access-Control-Allow-Origin', '*');

    $authHeader = $response->headers->get('WWW-Authenticate');
    expect($authHeader)->not->toBeNull()
        ->and($authHeader)->toContain('resource_metadata="'.url('/.well-known/oauth-protected-resource').'"');

    $exposeHeaders = $response->headers->get('Access-Control-Expose-Headers');
    expect($exposeHeaders)->toContain('WWW-Authenticate');
});

test('all 14 mcp tool definitions export valid json schema properties as object and never array', function () {
    $server = new McpServer($this->user);

    $response = $server->handle([
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'tools/list',
    ]);

    $tools = $response['result']['tools'];
    expect($tools)->toHaveCount(14);

    foreach ($tools as $tool) {
        $name = $tool['name'];
        $schema = $tool['inputSchema'];

        expect($schema['type'])->toBe('object');

        // Check serialized JSON representation of properties
        $propertiesJson = json_encode($schema['properties']);

        // In JSON Schema (and Google Gemini / OpenAPI parser), properties MUST be an object ({}), never []
        expect($propertiesJson)->not->toBe('[]', "Tool [{$name}] has properties serialized as array '[]' instead of object '{}'");
        expect(str_starts_with($propertiesJson, '{'))->toBeTrue("Tool [{$name}] properties does not serialize to a JSON object");
    }

    // Explicit check on the 3 tools with no parameters that previously returned 'properties' => []
    $zeroArgTools = ['get_balance_summary', 'list_funds', 'list_recurring_transactions'];
    foreach ($zeroArgTools as $toolName) {
        $tool = collect($tools)->firstWhere('name', $toolName);
        expect($tool)->not->toBeNull();

        $encoded = json_encode($tool['inputSchema']);
        expect($encoded)->toContain('"properties":{}')
            ->not->toContain('"properties":[]');
    }
});

test('mcp api controller handles json-rpc 2.0 batch requests', function () {
    Sanctum::actingAs($this->user);

    $batchPayload = [
        [
            'jsonrpc' => '2.0',
            'id' => 101,
            'method' => 'ping',
        ],
        [
            'jsonrpc' => '2.0',
            'id' => 102,
            'method' => 'tools/list',
        ],
    ];

    $response = $this->postJson('/api/mcp', $batchPayload);

    $response->assertOk();

    $data = $response->json();
    expect($data)->toBeArray()
        ->toHaveCount(2);

    // Ping response
    expect($data[0]['jsonrpc'])->toBe('2.0')
        ->and($data[0]['id'])->toBe(101)
        ->and($data[0]['result'])->toBeArray();

    // Tools list response
    expect($data[1]['jsonrpc'])->toBe('2.0')
        ->and($data[1]['id'])->toBe(102)
        ->and($data[1]['result']['tools'])->toHaveCount(14);
});

test('gemini public pkce token exchange succeeds without client secret', function () {
    $verifier = Str::random(64);
    $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');

    OAuthAuthCode::create([
        'id' => 'auth-code-pkce-public',
        'client_id' => $this->client->id,
        'user_id' => $this->user->id,
        'redirect_uri' => 'https://gemini.google.com/auth/callback',
        'code_challenge' => $challenge,
        'code_challenge_method' => 'S256',
        'expires_at' => now()->addMinutes(10),
    ]);

    // Public PKCE clients (like Gemini / browser clients) do NOT provide client_secret
    $response = $this->postJson('/oauth/token', [
        'grant_type' => 'authorization_code',
        'client_id' => $this->client->id,
        'code' => 'auth-code-pkce-public',
        'redirect_uri' => 'https://gemini.google.com/auth/callback',
        'code_verifier' => $verifier,
    ]);

    $response->assertOk()
        ->assertJsonStructure([
            'access_token',
            'token_type',
            'expires_in',
            'refresh_token',
            'scope',
        ]);

    $accessToken = $response->json('access_token');
    expect($accessToken)->not->toBeEmpty();

    // Verify the exchanged token can authenticate /api/mcp
    $mcpResponse = $this->withHeader('Authorization', 'Bearer '.$accessToken)
        ->postJson('/api/mcp', [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'tools/list',
        ]);

    $mcpResponse->assertOk()
        ->assertJsonPath('result.tools.0.name', 'list_transactions');
});

test('gemini oauth token exchange supports basic auth header for client credentials', function () {
    $verifier = Str::random(64);
    $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');

    OAuthAuthCode::create([
        'id' => 'auth-code-basic-auth',
        'client_id' => $this->client->id,
        'user_id' => $this->user->id,
        'redirect_uri' => 'https://gemini.google.com/auth/callback',
        'code_challenge' => $challenge,
        'code_challenge_method' => 'S256',
        'expires_at' => now()->addMinutes(10),
    ]);

    $basicCredentials = base64_encode($this->client->id.':'.$this->client->secret);

    $response = $this->withHeader('Authorization', 'Basic '.$basicCredentials)
        ->postJson('/oauth/token', [
            'grant_type' => 'authorization_code',
            'code' => 'auth-code-basic-auth',
            'redirect_uri' => 'https://gemini.google.com/auth/callback',
            'code_verifier' => $verifier,
        ]);

    $response->assertOk()
        ->assertJsonStructure(['access_token', 'refresh_token']);
});

test('mcp endpoint tracks and echoes streamable http mcp-session-id header', function () {
    Sanctum::actingAs($this->user);

    // 1. Client provides a session id -> echoed back
    $resWithSession = $this->withHeader('Mcp-Session-Id', 'gemini-custom-session-123')
        ->postJson('/api/mcp', [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'ping',
        ]);

    $resWithSession->assertOk()
        ->assertHeader('Mcp-Session-Id', 'gemini-custom-session-123');

    // 2. Client omits session id -> server generates and returns a UUID
    $this->flushHeaders();
    $resWithoutSession = $this->postJson('/api/mcp', [
        'jsonrpc' => '2.0',
        'id' => 2,
        'method' => 'ping',
    ]);

    $resWithoutSession->assertOk();
    $generatedSession = $resWithoutSession->headers->get('Mcp-Session-Id');
    expect($generatedSession)->not->toBeNull()
        ->and(Str::isUuid($generatedSession))->toBeTrue();
});

test('mcp server dynamically negotiates client requested protocol version', function () {
    $server = new McpServer($this->user);

    // Case 1: Client requests standard version 2024-11-05
    $res1 = $server->handle([
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'initialize',
        'params' => [
            'protocolVersion' => '2024-11-05',
        ],
    ]);
    expect($res1['result']['protocolVersion'])->toBe('2024-11-05');

    // Case 2: Client requests future or updated version 2025-03-01
    $res2 = $server->handle([
        'jsonrpc' => '2.0',
        'id' => 2,
        'method' => 'initialize',
        'params' => [
            'protocolVersion' => '2025-03-01',
        ],
    ]);
    expect($res2['result']['protocolVersion'])->toBe('2025-03-01');
});
