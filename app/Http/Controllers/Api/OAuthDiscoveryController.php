<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OAuthDiscoveryController extends Controller
{
    /**
     * RFC 8414: OAuth 2.0 Authorization Server Metadata
     */
    public function authorizationServerMetadata(Request $request): JsonResponse
    {
        $baseUrl = url('/');

        return response()->json([
            'issuer' => $baseUrl,
            'authorization_endpoint' => url('/oauth/authorize'),
            'token_endpoint' => url('/oauth/token'),
            'response_types_supported' => ['code'],
            'grant_types_supported' => ['authorization_code', 'refresh_token'],
            'code_challenge_methods_supported' => ['S256', 'plain'],
            'token_endpoint_auth_methods_supported' => ['client_secret_post', 'client_secret_basic'],
            'scopes_supported' => ['mcp'],
        ], 200, [
            'Content-Type' => 'application/json',
            'Access-Control-Allow-Origin' => '*',
        ]);
    }

    /**
     * RFC 9728: OAuth 2.0 Protected Resource Metadata
     */
    public function protectedResourceMetadata(Request $request): JsonResponse
    {
        $baseUrl = url('/');

        return response()->json([
            'resource' => url('/api/mcp'),
            'authorization_servers' => [
                $baseUrl,
            ],
            'scopes_supported' => ['mcp'],
            'bearer_methods_supported' => ['header'],
        ], 200, [
            'Content-Type' => 'application/json',
            'Access-Control-Allow-Origin' => '*',
        ]);
    }
}
