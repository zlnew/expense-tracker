<?php

namespace App\Http\Controllers\OAuth;

use App\Http\Controllers\Controller;
use App\Models\OAuthAuthCode;
use App\Models\OAuthClient;
use App\Models\OAuthRefreshToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class OAuthTokenController extends Controller
{
    /**
     * Issue OAuth 2.0 access & refresh tokens.
     */
    public function token(Request $request): JsonResponse
    {
        $clientId = $request->input('client_id');
        $clientSecret = $request->input('client_secret');

        // Check HTTP Basic Auth header if credentials not in body
        if (! $clientId && $request->hasHeader('Authorization')) {
            $authHeader = (string) $request->header('Authorization');
            if (str_starts_with($authHeader, 'Basic ')) {
                $decoded = base64_decode(substr($authHeader, 6));
                if ($decoded && str_contains($decoded, ':')) {
                    [$clientId, $clientSecret] = explode(':', $decoded, 2);
                    $clientId = urldecode($clientId);
                    $clientSecret = urldecode($clientSecret);
                }
            }
        }

        if (! $clientId) {
            return response()->json([
                'error' => 'invalid_client',
                'error_description' => 'Client ID is required.',
            ], 401);
        }

        $client = OAuthClient::find($clientId);

        if (! $client) {
            return response()->json([
                'error' => 'invalid_client',
                'error_description' => 'Client not found.',
            ], 401);
        }

        // Confidential client check (if secret supplied or no PKCE code_verifier provided)
        if ($clientSecret !== null && $clientSecret !== '') {
            if (! hash_equals($client->secret, (string) $clientSecret)) {
                return response()->json([
                    'error' => 'invalid_client',
                    'error_description' => 'Client authentication failed.',
                ], 401);
            }
        } elseif (! $request->input('code_verifier')) {
            return response()->json([
                'error' => 'invalid_client',
                'error_description' => 'Client secret or PKCE code_verifier is required.',
            ], 401);
        }

        $grantType = $request->input('grant_type');

        if ($grantType === 'authorization_code') {
            return $this->handleAuthorizationCode($request, $client);
        }

        if ($grantType === 'refresh_token') {
            return $this->handleRefreshToken($request, $client);
        }

        return response()->json([
            'error' => 'unsupported_grant_type',
            'error_description' => "Grant type '{$grantType}' is not supported.",
        ], 400);
    }

    protected function handleAuthorizationCode(Request $request, OAuthClient $client): JsonResponse
    {
        $code = (string) $request->input('code');
        $redirectUri = (string) $request->input('redirect_uri');

        if (! $code || ! $redirectUri) {
            return response()->json([
                'error' => 'invalid_request',
                'error_description' => 'Missing code or redirect_uri parameter.',
            ], 400);
        }

        $authCode = OAuthAuthCode::with('user')->find($code);

        if (! $authCode || $authCode->client_id !== $client->id || $authCode->isExpired()) {
            return response()->json([
                'error' => 'invalid_grant',
                'error_description' => 'The authorization code is invalid or expired.',
            ], 400);
        }

        if (! $client->matchesRedirectUri($redirectUri)) {
            return response()->json([
                'error' => 'invalid_grant',
                'error_description' => 'Redirect URI mismatch.',
            ], 400);
        }

        // Validate PKCE if code_challenge was provided
        if ($authCode->code_challenge) {
            $codeVerifier = (string) $request->input('code_verifier');
            if (! $codeVerifier) {
                return response()->json([
                    'error' => 'invalid_grant',
                    'error_description' => 'Missing code_verifier for PKCE.',
                ], 400);
            }

            $method = strtoupper((string) ($authCode->code_challenge_method ?: 'S256'));
            if ($method === 'S256') {
                $computedChallenge = rtrim(strtr(base64_encode(hash('sha256', $codeVerifier, true)), '+/', '-_'), '=');
                if (! hash_equals($authCode->code_challenge, $computedChallenge)) {
                    // Fallback to plain in case method was omitted and verifier matches directly
                    if (! hash_equals($authCode->code_challenge, $codeVerifier)) {
                        return response()->json([
                            'error' => 'invalid_grant',
                            'error_description' => 'PKCE verification failed.',
                        ], 400);
                    }
                }
            } else {
                if (! hash_equals($authCode->code_challenge, $codeVerifier)) {
                    return response()->json([
                        'error' => 'invalid_grant',
                        'error_description' => 'PKCE verification failed.',
                    ], 400);
                }
            }
        }

        $user = $authCode->user;

        // Consume authorization code (single-use)
        $authCode->delete();

        // Issue Sanctum access token
        $tokenName = 'OAuth: '.$client->name;
        $accessToken = $user->createToken($tokenName, ['*']);

        // Issue Refresh token (1 year validity)
        $refreshToken = Str::random(64);
        OAuthRefreshToken::create([
            'id' => $refreshToken,
            'access_token_id' => $accessToken->accessToken->id,
            'client_id' => $client->id,
            'user_id' => $user->id,
            'expires_at' => now()->addYear(),
        ]);

        return response()->json([
            'access_token' => $accessToken->plainTextToken,
            'token_type' => 'Bearer',
            'expires_in' => 31536000,
            'refresh_token' => $refreshToken,
            'scope' => 'mcp',
        ]);
    }

    protected function handleRefreshToken(Request $request, OAuthClient $client): JsonResponse
    {
        $refreshTokenString = (string) $request->input('refresh_token');

        if (! $refreshTokenString) {
            return response()->json([
                'error' => 'invalid_request',
                'error_description' => 'Missing refresh_token parameter.',
            ], 400);
        }

        $refreshToken = OAuthRefreshToken::with(['user', 'accessToken'])->find($refreshTokenString);

        if (! $refreshToken || $refreshToken->client_id !== $client->id || $refreshToken->isExpired()) {
            return response()->json([
                'error' => 'invalid_grant',
                'error_description' => 'The refresh token is invalid or expired.',
            ], 400);
        }

        $user = $refreshToken->user;

        // Revoke old access token and delete used refresh token
        $refreshToken->accessToken?->delete();
        $refreshToken->delete();

        // Issue new tokens
        $tokenName = 'OAuth: '.$client->name;
        $newAccessToken = $user->createToken($tokenName, ['*']);

        $newRefreshToken = Str::random(64);
        OAuthRefreshToken::create([
            'id' => $newRefreshToken,
            'access_token_id' => $newAccessToken->accessToken->id,
            'client_id' => $client->id,
            'user_id' => $user->id,
            'expires_at' => now()->addYear(),
        ]);

        return response()->json([
            'access_token' => $newAccessToken->plainTextToken,
            'token_type' => 'Bearer',
            'expires_in' => 31536000,
            'refresh_token' => $newRefreshToken,
            'scope' => 'mcp',
        ]);
    }
}
