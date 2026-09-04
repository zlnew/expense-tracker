<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PersonalAccessTokenController extends Controller
{
    /**
     * Allowed granular abilities for personal access tokens.
     */
    public const ALLOWED_ABILITIES = [
        'transactions:read',
        'transactions:write',
        'categories:read',
        'categories:write',
        'balances:read',
        'balances:write',
        'budgets:read',
        'budgets:write',
        'funds:read',
        'funds:write',
        'recurring_transactions:read',
        'recurring_transactions:write',
    ];

    public function index(Request $request): JsonResponse
    {
        $tokens = $request->user()->tokens()
            ->orderByDesc('id')
            ->get(['id', 'name', 'abilities', 'last_used_at', 'expires_at', 'created_at']);

        return response()->json($tokens);
    }

    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'abilities' => ['nullable', 'array'],
            'abilities.*' => ['string'],
            'expires_at' => ['nullable', 'date', 'after:today'],
        ]);

        $abilities = ! empty($validated['abilities']) ? array_values($validated['abilities']) : ['*'];
        $expiresAt = ! empty($validated['expires_at']) ? Carbon::parse($validated['expires_at']) : null;

        $token = $request->user()->createToken($validated['name'], $abilities, $expiresAt);

        if ($request->wantsJson()) {
            return response()->json([
                'token' => $token->accessToken,
                'plainTextToken' => $token->plainTextToken,
            ], 201);
        }

        return back()->with('token_created', [
            'token' => $token->accessToken,
            'plainTextToken' => $token->plainTextToken,
        ]);
    }

    public function destroy(Request $request, string $tokenId): JsonResponse|RedirectResponse
    {
        $token = $request->user()->tokens()->where('id', $tokenId)->firstOrFail();

        $token->delete();

        if ($request->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return back()->with('success', 'Personal access token revoked.');
    }
}
