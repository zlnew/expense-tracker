<?php

namespace App\Http\Controllers\OAuth;

use App\Http\Controllers\Controller;
use App\Models\OAuthAuthCode;
use App\Models\OAuthClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class OAuthAuthorizationController extends Controller
{
    /**
     * Display the authorization consent screen.
     */
    public function authorize(Request $request): Response|RedirectResponse
    {
        $clientId = (string) $request->query('client_id');
        $redirectUri = (string) $request->query('redirect_uri');
        $responseType = (string) $request->query('response_type');
        $state = (string) $request->query('state');
        $scope = (string) $request->query('scope', 'mcp');
        $codeChallenge = $request->query('code_challenge');
        $codeChallengeMethod = $request->query('code_challenge_method');

        $client = OAuthClient::find($clientId);

        if (! $client) {
            abort(400, 'Invalid client ID.');
        }

        if (! $client->matchesRedirectUri($redirectUri)) {
            abort(400, 'Redirect URI mismatch.');
        }

        if ($responseType !== 'code') {
            $delimiter = str_contains($redirectUri, '?') ? '&' : '?';

            return redirect()->away($redirectUri.$delimiter.http_build_query([
                'error' => 'unsupported_response_type',
                'state' => $state,
            ]));
        }

        return Inertia::render('OAuth/Authorize', [
            'client' => [
                'id' => $client->id,
                'name' => $client->name,
            ],
            'redirect_uri' => $redirectUri,
            'state' => $state,
            'scope' => $scope,
            'code_challenge' => $codeChallenge,
            'code_challenge_method' => $codeChallengeMethod,
        ]);
    }

    /**
     * Process user's decision on the authorization request.
     */
    public function approve(Request $request): RedirectResponse
    {
        $request->validate([
            'client_id' => ['required', 'string'],
            'redirect_uri' => ['required', 'string'],
            'action' => ['required', 'in:approve,deny'],
        ]);

        $clientId = $request->input('client_id');
        $redirectUri = $request->input('redirect_uri');
        $state = $request->input('state');
        $action = $request->input('action');

        $client = OAuthClient::find($clientId);

        if (! $client || ! $client->matchesRedirectUri($redirectUri)) {
            abort(400, 'Invalid client or redirect URI mismatch.');
        }

        $delimiter = str_contains($redirectUri, '?') ? '&' : '?';

        if ($action === 'deny') {
            return redirect()->away($redirectUri.$delimiter.http_build_query([
                'error' => 'access_denied',
                'state' => $state,
            ]));
        }

        // Generate authorization code (single-use, 10 min TTL)
        $code = Str::random(40);

        OAuthAuthCode::create([
            'id' => $code,
            'client_id' => $client->id,
            'user_id' => $request->user()->id,
            'redirect_uri' => $redirectUri,
            'code_challenge' => $request->input('code_challenge'),
            'code_challenge_method' => $request->input('code_challenge_method'),
            'expires_at' => now()->addMinutes(10),
        ]);

        $params = ['code' => $code];
        if ($state !== null && $state !== '') {
            $params['state'] = $state;
        }

        return redirect()->away($redirectUri.$delimiter.http_build_query($params));
    }
}
