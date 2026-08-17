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
 * with method, uri, the ability it requires, a one-line description,
 * a request body example, and a response shape example.
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
        'api.balances.transfer' => 'Transfer between balances',
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

    /**
     * Request body examples keyed by route name. GET/DELETE routes have
     * no request body (null). Amounts are in integer sen (Rp 250.00 = 25000).
     *
     * @var array<string, array<string, mixed>|null>
     */
    private const REQUEST_EXAMPLES = [
        'api.transactions' => null,
        'api.transactions.store' => [
            'balance_id' => 1,
            'category_id' => 3,
            'type' => 'expense',
            'date' => '2026-08-17',
            'amount' => 75000,
            'description' => 'Groceries at Indomaret',
            'budget_id' => null,
            'budget_item_id' => null,
        ],
        'api.transactions.update' => [
            'amount' => 80000,
            'description' => 'Groceries at Indomaret (updated)',
        ],
        'api.transactions.destroy' => null,
        'api.categories' => null,
        'api.categories.store' => [
            'type' => 'expense',
            'name' => 'Transport',
        ],
        'api.categories.update' => [
            'name' => 'Transportation',
        ],
        'api.categories.destroy' => null,
        'api.balances' => null,
        'api.balances.show' => null,
        'api.balances.store' => [
            'name' => 'Cash',
            'description' => 'Main cash on hand',
            'initial_amount' => 500000,
            'is_primary' => true,
        ],
        'api.balances.update' => [
            'name' => 'Cash Wallet',
            'description' => 'Daily spending cash',
            'initial_amount' => 500000,
            'is_primary' => true,
        ],
        'api.balances.destroy' => null,
        'api.balances.transfer' => [
            'from_account_id' => 1,
            'to_account_id' => 2,
            'date' => '2026-08-17',
            'amount' => 25000,
            'description' => 'Cash to savings',
        ],
        'api.budgets' => null,
        'api.budgets.store' => [
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-31',
            'cutoff_day' => 25,
            'carry_over' => false,
            'notes' => 'August 2026 budget',
            'items' => [
                ['category_id' => 1, 'type' => 'expense', 'planned_amount' => 500000],
                ['category_id' => 3, 'type' => 'expense', 'planned_amount' => 300000],
            ],
        ],
        'api.budgets.update' => [
            'notes' => 'Updated August budget',
            'items' => [
                ['category_id' => 1, 'type' => 'expense', 'planned_amount' => 550000],
                ['category_id' => 3, 'type' => 'expense', 'planned_amount' => 350000],
            ],
        ],
        'api.budgets.destroy' => null,
        'api.budgets.set-active' => null,
        'api.funds' => null,
        'api.funds.upcoming' => null,
        'api.funds.store' => [
            'name' => 'Car Maintenance',
            'target_amount' => 2000000,
            'cadence' => 'monthly',
            'contribution_amount' => 200000,
            'category_id' => 5,
            'next_due' => '2026-09-01',
            'due_interval_months' => 1,
            'notes' => 'Oil change, tires, service',
        ],
        'api.funds.update' => [
            'target_amount' => 2500000,
            'notes' => 'Updated car maintenance fund',
        ],
        'api.funds.destroy' => null,
        'api.funds.contributions.store' => [
            'date' => '2026-08-17',
            'amount' => 200000,
        ],
        'api.funds.withdrawals.store' => [
            'date' => '2026-08-17',
            'amount' => 500000,
            'description' => 'Oil change',
        ],
        'api.recurring-transactions' => null,
        'api.recurring-transactions.show' => null,
        'api.recurring-transactions.store' => [
            'type' => 'expense',
            'balance_id' => 1,
            'category_id' => 2,
            'amount' => 150000,
            'description' => 'Monthly internet bill',
            'frequency' => 'monthly',
            'start_date' => '2026-08-01',
            'end_date' => null,
            'next_run_date' => '2026-09-01',
            'is_active' => true,
        ],
        'api.recurring-transactions.update' => [
            'amount' => 160000,
            'description' => 'Monthly internet bill (updated)',
        ],
        'api.recurring-transactions.destroy' => null,
    ];

    /**
     * Response shape examples keyed by route name. Uses realistic IRP
     * values and DTO field names.
     *
     * @var array<string, mixed>
     */
    private const RESPONSE_EXAMPLES = [
        'api.transactions' => [
            [
                'id' => 1,
                'user_id' => 1,
                'balance_id' => 1,
                'budget_id' => null,
                'budget_item_id' => null,
                'category_id' => 3,
                'type' => 'expense',
                'date' => '2026-08-17',
                'amount' => 75000,
                'description' => 'Groceries at Indomaret',
                'cycle_month' => 8,
                'cycle_year' => 2026,
                'transfer_group_id' => null,
                'balance' => [
                    'id' => 1,
                    'user_id' => 1,
                    'name' => 'Cash',
                    'description' => 'Main cash on hand',
                    'initial_amount' => 500000,
                    'final_amount' => 425000,
                    'is_primary' => true,
                ],
                'category' => [
                    'id' => 3,
                    'type' => 'expense',
                    'name' => 'Groceries',
                ],
            ],
        ],
        'api.transactions.store' => [
            'id' => 2,
            'user_id' => 1,
            'balance_id' => 1,
            'budget_id' => null,
            'budget_item_id' => null,
            'category_id' => 3,
            'type' => 'expense',
            'date' => '2026-08-17',
            'amount' => 75000,
            'description' => 'Groceries at Indomaret',
            'cycle_month' => 8,
            'cycle_year' => 2026,
            'transfer_group_id' => null,
            'balance' => [
                'id' => 1,
                'user_id' => 1,
                'name' => 'Cash',
                'description' => 'Main cash on hand',
                'initial_amount' => 500000,
                'final_amount' => 425000,
                'is_primary' => true,
            ],
            'category' => [
                'id' => 3,
                'type' => 'expense',
                'name' => 'Groceries',
            ],
        ],
        'api.transactions.update' => [
            'id' => 1,
            'user_id' => 1,
            'balance_id' => 1,
            'budget_id' => null,
            'budget_item_id' => null,
            'category_id' => 3,
            'type' => 'expense',
            'date' => '2026-08-17',
            'amount' => 80000,
            'description' => 'Groceries at Indomaret (updated)',
            'cycle_month' => 8,
            'cycle_year' => 2026,
            'transfer_group_id' => null,
            'balance' => [
                'id' => 1,
                'user_id' => 1,
                'name' => 'Cash',
                'description' => 'Main cash on hand',
                'initial_amount' => 500000,
                'final_amount' => 420000,
                'is_primary' => true,
            ],
            'category' => [
                'id' => 3,
                'type' => 'expense',
                'name' => 'Groceries',
            ],
        ],
        'api.transactions.destroy' => null,
        'api.categories' => [
            [
                'id' => 1,
                'type' => 'income',
                'name' => 'Salary',
            ],
            [
                'id' => 3,
                'type' => 'expense',
                'name' => 'Groceries',
            ],
        ],
        'api.categories.store' => [
            'id' => 5,
            'type' => 'expense',
            'name' => 'Transport',
        ],
        'api.categories.update' => [
            'id' => 5,
            'type' => 'expense',
            'name' => 'Transportation',
        ],
        'api.categories.destroy' => null,
        'api.balances' => [
            [
                'id' => 1,
                'user_id' => 1,
                'name' => 'Cash',
                'description' => 'Main cash on hand',
                'initial_amount' => 500000,
                'final_amount' => 425000,
                'is_primary' => true,
            ],
            [
                'id' => 2,
                'user_id' => 1,
                'name' => 'Savings',
                'description' => 'Emergency fund',
                'initial_amount' => 5000000,
                'final_amount' => 5250000,
                'is_primary' => false,
            ],
        ],
        'api.balances.show' => [
            'id' => 1,
            'user_id' => 1,
            'name' => 'Cash',
            'description' => 'Main cash on hand',
            'initial_amount' => 500000,
            'final_amount' => 425000,
            'is_primary' => true,
        ],
        'api.balances.store' => [
            'id' => 3,
            'user_id' => 1,
            'name' => 'Cash',
            'description' => 'Main cash on hand',
            'initial_amount' => 500000,
            'final_amount' => 500000,
            'is_primary' => true,
        ],
        'api.balances.update' => [
            'id' => 1,
            'user_id' => 1,
            'name' => 'Cash Wallet',
            'description' => 'Daily spending cash',
            'initial_amount' => 500000,
            'final_amount' => 425000,
            'is_primary' => true,
        ],
        'api.balances.destroy' => null,
        'api.balances.transfer' => [
            'message' => 'Transfer completed',
        ],
        'api.budgets' => [
            [
                'id' => 1,
                'user_id' => 1,
                'period_start' => '2026-08-01',
                'period_end' => '2026-08-31',
                'cutoff_day' => 25,
                'is_active' => true,
                'carry_over' => false,
                'notes' => 'August 2026 budget',
                'updated_at' => '2026-08-01 10:00:00',
                'items' => [
                    [
                        'id' => 1,
                        'budget_id' => 1,
                        'category_id' => 1,
                        'type' => 'expense',
                        'planned_amount' => 500000,
                        'actual_amount' => 350000,
                        'diff_amount' => 150000,
                        'category' => [
                            'id' => 1,
                            'type' => 'expense',
                            'name' => 'Groceries',
                        ],
                    ],
                ],
            ],
        ],
        'api.budgets.store' => [
            'id' => 2,
            'user_id' => 1,
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-31',
            'cutoff_day' => 25,
            'is_active' => false,
            'carry_over' => false,
            'notes' => 'August 2026 budget',
            'updated_at' => '2026-08-17 14:00:00',
            'items' => [],
        ],
        'api.budgets.update' => [
            'id' => 1,
            'user_id' => 1,
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-31',
            'cutoff_day' => 25,
            'is_active' => true,
            'carry_over' => false,
            'notes' => 'Updated August budget',
            'updated_at' => '2026-08-17 14:30:00',
            'items' => [],
        ],
        'api.budgets.destroy' => null,
        'api.budgets.set-active' => [
            'id' => 1,
            'user_id' => 1,
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-31',
            'cutoff_day' => 25,
            'is_active' => true,
            'carry_over' => false,
            'notes' => 'August 2026 budget',
            'updated_at' => '2026-08-17 14:00:00',
            'items' => [],
        ],
        'api.funds' => [
            [
                'id' => 1,
                'user_id' => 1,
                'name' => 'Car Maintenance',
                'target_amount' => 2000000,
                'cadence' => 'monthly',
                'contribution_amount' => 200000,
                'category_id' => 5,
                'next_due' => '2026-09-01',
                'due_interval_months' => 1,
                'notes' => 'Oil change, tires, service',
                'accumulated' => 1200000,
                'percent' => 60,
                'status' => 'on_track',
                'auto_contribution' => null,
                'last_contribution_date' => '2026-08-01',
                'category' => [
                    'id' => 5,
                    'type' => 'expense',
                    'name' => 'Automotive',
                ],
            ],
        ],
        'api.funds.upcoming' => [
            [
                'id' => 1,
                'user_id' => 1,
                'name' => 'Car Maintenance',
                'target_amount' => 2000000,
                'cadence' => 'monthly',
                'contribution_amount' => 200000,
                'category_id' => 5,
                'next_due' => '2026-09-01',
                'due_interval_months' => 1,
                'notes' => 'Oil change, tires, service',
                'accumulated' => 1200000,
                'percent' => 60,
                'status' => 'on_track',
                'auto_contribution' => null,
                'last_contribution_date' => '2026-08-01',
                'category' => [
                    'id' => 5,
                    'type' => 'expense',
                    'name' => 'Automotive',
                ],
                'days_until_due' => 15,
                'projected_shortfall' => 0,
            ],
        ],
        'api.funds.store' => [
            'id' => 2,
            'user_id' => 1,
            'name' => 'Car Maintenance',
            'target_amount' => 2000000,
            'cadence' => 'monthly',
            'contribution_amount' => 200000,
            'category_id' => 5,
            'next_due' => '2026-09-01',
            'due_interval_months' => 1,
            'notes' => 'Oil change, tires, service',
            'accumulated' => 0,
            'percent' => 0,
            'status' => 'on_track',
            'auto_contribution' => null,
            'last_contribution_date' => null,
            'category' => null,
        ],
        'api.funds.update' => [
            'id' => 1,
            'user_id' => 1,
            'name' => 'Car Maintenance',
            'target_amount' => 2500000,
            'cadence' => 'monthly',
            'contribution_amount' => 200000,
            'category_id' => 5,
            'next_due' => '2026-09-01',
            'due_interval_months' => 1,
            'notes' => 'Updated car maintenance fund',
            'accumulated' => 1200000,
            'percent' => 48,
            'status' => 'on_track',
            'auto_contribution' => null,
            'last_contribution_date' => '2026-08-01',
            'category' => [
                'id' => 5,
                'type' => 'expense',
                'name' => 'Automotive',
            ],
        ],
        'api.funds.destroy' => null,
        'api.funds.contributions.store' => [
            'id' => 1,
            'user_id' => 1,
            'name' => 'Car Maintenance',
            'target_amount' => 2000000,
            'cadence' => 'monthly',
            'contribution_amount' => 200000,
            'category_id' => 5,
            'next_due' => '2026-09-01',
            'due_interval_months' => 1,
            'notes' => 'Oil change, tires, service',
            'accumulated' => 1400000,
            'percent' => 70,
            'status' => 'on_track',
            'auto_contribution' => null,
            'last_contribution_date' => '2026-08-17',
            'category' => [
                'id' => 5,
                'type' => 'expense',
                'name' => 'Automotive',
            ],
        ],
        'api.funds.withdrawals.store' => [
            'id' => 1,
            'user_id' => 1,
            'name' => 'Car Maintenance',
            'target_amount' => 2000000,
            'cadence' => 'monthly',
            'contribution_amount' => 200000,
            'category_id' => 5,
            'next_due' => '2026-09-01',
            'due_interval_months' => 1,
            'notes' => 'Oil change, tires, service',
            'accumulated' => 700000,
            'percent' => 35,
            'status' => 'on_track',
            'auto_contribution' => null,
            'last_contribution_date' => '2026-08-01',
            'category' => [
                'id' => 5,
                'type' => 'expense',
                'name' => 'Automotive',
            ],
        ],
        'api.recurring-transactions' => [
            [
                'id' => 1,
                'type' => 'expense',
                'balance_id' => 1,
                'category_id' => 2,
                'amount' => 150000,
                'description' => 'Monthly internet bill',
                'frequency' => 'monthly',
                'start_date' => '2026-08-01',
                'end_date' => null,
                'next_run_date' => '2026-09-01',
                'is_active' => true,
                'balance' => [
                    'id' => 1,
                    'user_id' => 1,
                    'name' => 'Cash',
                    'description' => 'Main cash on hand',
                    'initial_amount' => 500000,
                    'final_amount' => 425000,
                    'is_primary' => true,
                ],
                'category' => [
                    'id' => 2,
                    'type' => 'expense',
                    'name' => 'Utilities',
                ],
            ],
        ],
        'api.recurring-transactions.show' => [
            'id' => 1,
            'type' => 'expense',
            'balance_id' => 1,
            'category_id' => 2,
            'amount' => 150000,
            'description' => 'Monthly internet bill',
            'frequency' => 'monthly',
            'start_date' => '2026-08-01',
            'end_date' => null,
            'next_run_date' => '2026-09-01',
            'is_active' => true,
            'balance' => [
                'id' => 1,
                'user_id' => 1,
                'name' => 'Cash',
                'description' => 'Main cash on hand',
                'initial_amount' => 500000,
                'final_amount' => 425000,
                'is_primary' => true,
            ],
            'category' => [
                'id' => 2,
                'type' => 'expense',
                'name' => 'Utilities',
            ],
        ],
        'api.recurring-transactions.store' => [
            'id' => 2,
            'type' => 'expense',
            'balance_id' => 1,
            'category_id' => 2,
            'amount' => 150000,
            'description' => 'Monthly internet bill',
            'frequency' => 'monthly',
            'start_date' => '2026-08-01',
            'end_date' => null,
            'next_run_date' => '2026-09-01',
            'is_active' => true,
            'balance' => null,
            'category' => null,
        ],
        'api.recurring-transactions.update' => [
            'id' => 1,
            'type' => 'expense',
            'balance_id' => 1,
            'category_id' => 2,
            'amount' => 160000,
            'description' => 'Monthly internet bill (updated)',
            'frequency' => 'monthly',
            'start_date' => '2026-08-01',
            'end_date' => null,
            'next_run_date' => '2026-09-01',
            'is_active' => true,
            'balance' => [
                'id' => 1,
                'user_id' => 1,
                'name' => 'Cash',
                'description' => 'Main cash on hand',
                'initial_amount' => 500000,
                'final_amount' => 425000,
                'is_primary' => true,
            ],
            'category' => [
                'id' => 2,
                'type' => 'expense',
                'name' => 'Utilities',
            ],
        ],
        'api.recurring-transactions.destroy' => null,
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
            $name = $route->getName();

            $contract[$feature][] = [
                'method' => $this->methods($route),
                'uri' => '/'.$route->uri(),
                'ability' => $ability,
                'description' => $this->descriptionFor($route),
                'request_example' => $name !== null ? (self::REQUEST_EXAMPLES[$name] ?? null) : null,
                'response_example' => $name !== null ? (self::RESPONSE_EXAMPLES[$name] ?? null) : null,
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
