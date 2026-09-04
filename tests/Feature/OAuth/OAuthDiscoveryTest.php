<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('rfc 8414 oauth authorization server metadata discovery endpoint returns valid spec', function () {
    $response = $this->getJson('/.well-known/oauth-authorization-server');

    $response->assertOk()
        ->assertHeader('Access-Control-Allow-Origin', '*')
        ->assertJsonStructure([
            'issuer',
            'authorization_endpoint',
            'token_endpoint',
            'response_types_supported',
            'grant_types_supported',
            'code_challenge_methods_supported',
            'token_endpoint_auth_methods_supported',
            'scopes_supported',
        ]);

    expect($response->json('authorization_endpoint'))->toBe(url('/oauth/authorize'))
        ->and($response->json('token_endpoint'))->toBe(url('/oauth/token'))
        ->and($response->json('grant_types_supported'))->toContain('authorization_code', 'refresh_token')
        ->and($response->json('code_challenge_methods_supported'))->toContain('S256');
});

test('rfc 9728 oauth protected resource metadata discovery endpoint returns valid spec', function () {
    $response = $this->getJson('/.well-known/oauth-protected-resource');

    $response->assertOk()
        ->assertHeader('Access-Control-Allow-Origin', '*')
        ->assertJsonStructure([
            'resource',
            'authorization_servers',
            'scopes_supported',
            'bearer_methods_supported',
        ]);

    expect($response->json('resource'))->toBe(url('/api/mcp'))
        ->and($response->json('authorization_servers'))->toContain(url('/'))
        ->and($response->json('bearer_methods_supported'))->toContain('header');
});

test('unauthenticated request to api mcp returns 401 with rfc 9728 WWW-Authenticate header', function () {
    $response = $this->postJson('/api/mcp', [
        'jsonrpc' => '2.0',
        'method' => 'tools/list',
        'id' => 1,
    ]);

    $response->assertUnauthorized();

    $authHeader = $response->headers->get('WWW-Authenticate');
    expect($authHeader)->not->toBeNull()
        ->and($authHeader)->toContain('resource_metadata="'.url('/.well-known/oauth-protected-resource').'"');
});

test('unauthenticated GET to api mcp returns 401 with rfc 9728 WWW-Authenticate header', function () {
    $response = $this->getJson('/api/mcp');

    $response->assertUnauthorized();

    $authHeader = $response->headers->get('WWW-Authenticate');
    expect($authHeader)->not->toBeNull()
        ->and($authHeader)->toContain('resource_metadata="'.url('/.well-known/oauth-protected-resource').'"');
});
