<?php

namespace App\Http\Middleware;

use App\DTO\BalanceData;
use App\DTO\BudgetData;
use App\DTO\CategoryData;
use App\Models\Balance;
use App\Models\Budget;
use App\Models\Category;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'locale' => config('app.locale'),
            'auth' => [
                'user' => $request->user(),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'success' => fn () => $request->session()->get('success'),
            // Shared everywhere so the global quick-add FAB (mobile bottom nav)
            // can open the transaction create dialog from any page. Optional()
            // so it's only evaluated when the client actually requests them.
            'balances' => Inertia::optional(fn () => $request->user()
                ? BalanceData::collect(Balance::where('user_id', $request->user()->id)->get())
                : []),
            'budgets' => Inertia::optional(fn () => $request->user()
                ? BudgetData::collect(Budget::where('user_id', $request->user()->id)->with('items.category')->get())
                : []),
            'categories' => Inertia::optional(fn () => $request->user()
                ? CategoryData::collect(Category::where('user_id', $request->user()->id)->get())
                : []),
            'primaryBalanceId' => Inertia::optional(fn () => $request->user()
                ? (Balance::where('user_id', $request->user()->id)->where('is_primary', true)->value('id'))
                : null),
            'activeBudgetId' => Inertia::optional(fn () => $request->user()
                ? (Budget::where('user_id', $request->user()->id)->where('is_active', true)->value('id'))
                : null),
        ];
    }
}
