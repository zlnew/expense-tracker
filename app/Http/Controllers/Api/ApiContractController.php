<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;
use Illuminate\Support\Str;

/**
 * GET /api/contract — self-describing API discovery for authenticated
 * tokens. Lists every API route grouped by feature (the ability prefix),
 * with method, uri, the ability it requires, and a one-line description.
 *
 * The contract only surfaces routes the caller's token can actually use:
 * a token with `transactions:read` alone sees exactly the read routes it
 * is allowed to call, never write routes it would 403 on.
 */
class ApiContractController extends Controller
{
    /**
     * One-line summaries keyed by route name. Unknown routes fall back to
     * a generated description so new endpoints never break the contract.
     *
     * @var array<string, string>
     */
    private const DESCRIPTIONS = [
        'api.transactions' => 'List transactions',
        'api.transactions.store' => 'Create a transaction',
        'api.transactions.update' => 'Update a transaction',
        'api.transactions.destroy' => 'Delete a transaction',
        'api.categories' => 'List categories',
        'api.categories.store' => 'Create a category',
        'api.categories.update' => 'Update a category',
        'api.categories.destroy' => 'Delete a category',
        'api.balances' => 'List balances',
        'api.balances.show' => 'Show a balance',
        'api.balances.store' => 'Create a balance',
        'api.balances.update' => 'Update a balance',
        'api.balances.destroy' => 'Delete a balance',
        'api.budgets' => 'List budgets',
        'api.budgets.store' => 'Create a budget',
        'api.budgets.update' => 'Update a budget',
        'api.budgets.destroy' => 'Delete a budget',
        'api.budgets.set-active' => 'Set the active budget',
        'api.funds' => 'List funds',
        'api.funds.upcoming' => 'List upcoming fund dues',
        'api.funds.store' => 'Create a fund',
        'api.funds.update' => 'Update a fund',
        'api.funds.destroy' => 'Delete a fund',
        'api.funds.contributions.store' => 'Record a fund contribution',
        'api.funds.withdrawals.store' => 'Record a fund withdrawal',
        'api.recurring-transactions' => 'List recurring transactions',
        'api.recurring-transactions.show' => 'Show a recurring transaction',
        'api.recurring-transactions.store' => 'Create a recurring transaction',
        'api.recurring-transactions.update' => 'Update a recurring transaction',
        'api.recurring-transactions.destroy' => 'Delete a recurring transaction',
    ];

    public function __invoke(Request $request): JsonResponse
    {
        $contract = [];

        foreach (RouteFacade::getRoutes()->getRoutes() as $route) {
            if (! str_starts_with($route->uri(), 'api/')) {
                continue;
            }

            $ability = $this->abilityFor($route);

            if ($ability === null) {
                continue;
            }

            // Only surface routes this token can actually call.
            if (! $request->user()->tokenCan($ability)) {
                continue;
            }

            $feature = Str::before($ability, ':');

            $contract[$feature][] = [
                'method' => $this->methods($route),
                'uri' => '/'.$route->uri(),
                'ability' => $ability,
                'description' => $this->descriptionFor($route),
            ];
        }

        return response()->json($contract);
    }

    /**
     * Extract the required ability from the route's `abilities:` middleware
     * (the alias registered in bootstrap/app.php). Routes without one — the
     * contract itself, web routes — are skipped.
     */
    private function abilityFor(Route $route): ?string
    {
        foreach ($route->gatherMiddleware() as $middleware) {
            if (is_string($middleware) && str_starts_with($middleware, 'abilities:')) {
                return substr($middleware, strlen('abilities:'));
            }
        }

        return null;
    }

    /**
     * Normalize the route's HTTP methods to a display string. GET routes
     * also register HEAD internally — drop it so the contract reads
     * GET/POST/PATCH/DELETE as a consumer expects.
     */
    private function methods(Route $route): string
    {
        $methods = array_values(array_filter(
            $route->methods(),
            fn (string $method) => $method !== 'HEAD',
        ));

        return implode('/', $methods);
    }

    private function descriptionFor(Route $route): string
    {
        $name = $route->getName();

        if ($name !== null && isset(self::DESCRIPTIONS[$name])) {
            return self::DESCRIPTIONS[$name];
        }

        $action = Str::afterLast($route->getActionName() ?? '', '@');

        return match ($action) {
            'index' => 'List '.Str::headline($this->featureFromUri($route)),
            'store' => 'Create a '.Str::singular(Str::headline($this->featureFromUri($route))),
            'update' => 'Update a '.Str::singular(Str::headline($this->featureFromUri($route))),
            'destroy' => 'Delete a '.Str::singular(Str::headline($this->featureFromUri($route))),
            default => Str::headline($action).' '.Str::headline($this->featureFromUri($route)),
        };
    }

    private function featureFromUri(Route $route): string
    {
        return Str::after($route->uri(), 'api/');
    }
}
