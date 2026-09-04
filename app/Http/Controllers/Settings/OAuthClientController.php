<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\OAuthClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class OAuthClientController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $clients = OAuthClient::query()
            ->where('user_id', $request->user()->id)
            ->latest()
            ->get(['id', 'name', 'redirect_uri', 'created_at']);

        return response()->json($clients);
    }

    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'redirect_uri' => ['required', 'url', 'max:2000'],
        ]);

        $clientId = Str::random(32);
        $clientSecret = Str::random(64);

        $client = OAuthClient::create([
            'id' => $clientId,
            'user_id' => $request->user()->id,
            'name' => $validated['name'],
            'secret' => $clientSecret,
            'redirect_uri' => $validated['redirect_uri'],
        ]);

        if ($request->wantsJson()) {
            return response()->json([
                'client' => $client,
                'secret' => $clientSecret,
            ], 201);
        }

        return back()->with('oauth_client_created', [
            'id' => $client->id,
            'name' => $client->name,
            'secret' => $clientSecret,
            'redirect_uri' => $client->redirect_uri,
        ]);
    }

    public function destroy(Request $request, string $clientId): JsonResponse|RedirectResponse
    {
        $client = OAuthClient::where('id', $clientId)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $client->delete();

        if ($request->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return back()->with('success', 'Connected app removed.');
    }
}
