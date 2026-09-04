<?php

use App\Models\OAuthAuthCode;
use App\Models\OAuthClient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create([
        'name' => 'Maulana',
        'email' => 'maul@example.com',
    ]);

    $this->client = OAuthClient::create([
        'id' => 'test-gemini-client-id-12345',
        'user_id' => $this->user->id,
        'name' => 'Google Gemini',
        'secret' => 'super-secret-key-67890',
        'redirect_uri' => 'https://gemini.google.com/auth/callback',
    ]);
});

test('oauth authorize endpoint redirects unauthenticated users to login', function () {
    $response = $this->get('/oauth/authorize?client_id='.$this->client->id.'&redirect_uri=https://gemini.google.com/auth/callback&response_type=code');

    $response->assertRedirect('/login');
});

test('oauth authorize endpoint validates client id and redirect uri', function () {
    $this->actingAs($this->user);

    // Invalid client id -> 400
    $resBadClient = $this->get('/oauth/authorize?client_id=nonexistent&redirect_uri=https://gemini.google.com/auth/callback&response_type=code');
    $resBadClient->assertStatus(400);

    // Mismatched redirect uri -> 400
    $resBadUri = $this->get('/oauth/authorize?client_id='.$this->client->id.'&redirect_uri=https://evil.com/callback&response_type=code');
    $resBadUri->assertStatus(400);
});

test('oauth authorize user approval generates code and redirects to callback', function () {
    $this->actingAs($this->user);

    $response = $this->post('/oauth/authorize', [
        'client_id' => $this->client->id,
        'redirect_uri' => 'https://gemini.google.com/auth/callback',
        'state' => 'xyz-state-123',
        'action' => 'approve',
        'code_challenge' => 'sample-challenge',
        'code_challenge_method' => 'S256',
    ]);

    $response->assertRedirect();
    $targetUrl = $response->headers->get('Location');
    expect($targetUrl)->toContain('https://gemini.google.com/auth/callback')
        ->and($targetUrl)->toContain('code=')
        ->and($targetUrl)->toContain('state=xyz-state-123');

    $authCode = OAuthAuthCode::where('client_id', $this->client->id)->first();
    expect($authCode)->not->toBeNull()
        ->and($authCode->user_id)->toBe($this->user->id)
        ->and($authCode->code_challenge)->toBe('sample-challenge');
});

test('oauth authorize user denial redirects with access_denied error', function () {
    $this->actingAs($this->user);

    $response = $this->post('/oauth/authorize', [
        'client_id' => $this->client->id,
        'redirect_uri' => 'https://gemini.google.com/auth/callback',
        'state' => 'xyz-state-123',
        'action' => 'deny',
    ]);

    $response->assertRedirect('https://gemini.google.com/auth/callback?error=access_denied&state=xyz-state-123');
});

test('oauth token exchange with authorization_code and pkce S256 issues working tokens', function () {
    // 1. Generate code verifier and S256 challenge
    $verifier = Str::random(64);
    $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');

    // 2. Create auth code
    $authCode = OAuthAuthCode::create([
        'id' => 'auth-code-test-12345',
        'client_id' => $this->client->id,
        'user_id' => $this->user->id,
        'redirect_uri' => 'https://gemini.google.com/auth/callback',
        'code_challenge' => $challenge,
        'code_challenge_method' => 'S256',
        'expires_at' => now()->addMinutes(10),
    ]);

    // 3. Exchange token via POST /oauth/token
    $response = $this->postJson('/oauth/token', [
        'grant_type' => 'authorization_code',
        'client_id' => $this->client->id,
        'client_secret' => $this->client->secret,
        'code' => 'auth-code-test-12345',
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
    $refreshToken = $response->json('refresh_token');

    // Auth code was consumed
    expect(OAuthAuthCode::find('auth-code-test-12345'))->toBeNull();

    // 4. Test accessing /api/mcp using this OAuth Bearer token
    $mcpResponse = $this->withHeader('Authorization', 'Bearer '.$accessToken)
        ->postJson('/api/mcp', [
            'jsonrpc' => '2.0',
            'id' => 100,
            'method' => 'tools/call',
            'params' => [
                'name' => 'list_categories',
                'arguments' => [],
            ],
        ]);

    $mcpResponse->assertOk()
        ->assertJsonPath('id', 100);

    // 5. Test token refresh
    $refreshResponse = $this->postJson('/oauth/token', [
        'grant_type' => 'refresh_token',
        'client_id' => $this->client->id,
        'client_secret' => $this->client->secret,
        'refresh_token' => $refreshToken,
    ]);

    $refreshResponse->assertOk()
        ->assertJsonStructure(['access_token', 'refresh_token']);

    $newAccessToken = $refreshResponse->json('access_token');
    expect($newAccessToken)->not->toBe($accessToken);

    // New access token works
    $newMcpRes = $this->withHeader('Authorization', 'Bearer '.$newAccessToken)
        ->postJson('/api/mcp', [
            'jsonrpc' => '2.0',
            'id' => 101,
            'method' => 'tools/call',
            'params' => [
                'name' => 'list_categories',
                'arguments' => [],
            ],
        ]);
    $newMcpRes->assertOk()->assertJsonPath('id', 101);
});

test('oauth token endpoint rejects invalid client secret or invalid verifier', function () {
    $verifier = Str::random(64);
    $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');

    OAuthAuthCode::create([
        'id' => 'auth-code-fail-test',
        'client_id' => $this->client->id,
        'user_id' => $this->user->id,
        'redirect_uri' => 'https://gemini.google.com/auth/callback',
        'code_challenge' => $challenge,
        'code_challenge_method' => 'S256',
        'expires_at' => now()->addMinutes(10),
    ]);

    // Wrong client secret -> 401
    $resBadSecret = $this->postJson('/oauth/token', [
        'grant_type' => 'authorization_code',
        'client_id' => $this->client->id,
        'client_secret' => 'wrong-secret',
        'code' => 'auth-code-fail-test',
        'redirect_uri' => 'https://gemini.google.com/auth/callback',
        'code_verifier' => $verifier,
    ]);
    $resBadSecret->assertStatus(401);

    // Wrong code verifier -> 400
    $resBadVerifier = $this->postJson('/oauth/token', [
        'grant_type' => 'authorization_code',
        'client_id' => $this->client->id,
        'client_secret' => $this->client->secret,
        'code' => 'auth-code-fail-test',
        'redirect_uri' => 'https://gemini.google.com/auth/callback',
        'code_verifier' => 'wrong-verifier',
    ]);
    $resBadVerifier->assertStatus(400);
});

test('artisan oauth:client command creates client successfully', function () {
    Artisan::call('oauth:client', [
        'name' => 'Gemini Assistant',
        'redirect_uri' => 'https://gemini.google.com/auth/mcp/callback',
        '--user' => $this->user->id,
    ]);

    $client = OAuthClient::where('name', 'Gemini Assistant')->first();
    expect($client)->not->toBeNull()
        ->and($client->user_id)->toBe($this->user->id)
        ->and($client->redirect_uri)->toBe('https://gemini.google.com/auth/mcp/callback')
        ->and(strlen($client->secret))->toBe(64);
});

test('settings oauth clients endpoints allow managing connected apps', function () {
    $this->actingAs($this->user);

    // 1. List
    $listRes = $this->getJson('/settings/oauth-clients');
    $listRes->assertOk()
        ->assertJsonCount(1);

    // 2. Store
    $storeRes = $this->postJson('/settings/oauth-clients', [
        'name' => 'Custom AI Agent',
        'redirect_uri' => 'https://agent.example.com/callback',
    ]);
    $storeRes->assertCreated()
        ->assertJsonStructure(['client' => ['id', 'name', 'redirect_uri'], 'secret']);

    $newClientId = $storeRes->json('client.id');

    // 3. Delete
    $delRes = $this->deleteJson('/settings/oauth-clients/'.$newClientId);
    $delRes->assertOk();

    expect(OAuthClient::find($newClientId))->toBeNull();
});
