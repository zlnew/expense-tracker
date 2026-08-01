<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Reject requests whose route-model-bound resource does not belong to the
 * authenticated user. Applies to any route whose parameter is an Eloquent
 * model carrying a user_id column (transactions, balances, budgets, ...).
 */
class EnsureOwnership
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            abort(403);
        }

        foreach ($request->route()->parameters() as $parameter) {
            if (! $parameter instanceof Model) {
                continue;
            }

            $ownerKey = $parameter->getAttribute('user_id');

            if ($ownerKey !== null && (int) $ownerKey !== (int) $user->id) {
                abort(403);
            }

            // Only the first model parameter is the primary resource; nested
            // relation bindings (if any) are covered by their own checks.
            break;
        }

        return $next($request);
    }
}
