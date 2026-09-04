<?php

use App\Actions\GetImpendingDrains;
use App\Http\Controllers\Api\OAuthDiscoveryController;
use App\Http\Controllers\BalanceController;
use App\Http\Controllers\BudgetController;
use App\Http\Controllers\BudgetTransactionsController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FundController;
use App\Http\Controllers\OAuth\OAuthAuthorizationController;
use App\Http\Controllers\OAuth\OAuthTokenController;
use App\Http\Controllers\RecurringTransactionController;
use App\Http\Controllers\TransactionController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

Route::inertia('/', 'Welcome', [
    'canRegister' => Features::enabled(Features::registration()),
])->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    // Session-auth mirror for the 30/60/90 window filter — browser sessions
    // can't call /api/* (Sanctum token-only), so the card fetches here.
    Route::get('dashboard/impending-drains', function (Request $request) {
        $window = max(1, min(365, (int) $request->integer('window', 60)));

        return response()->json(GetImpendingDrains::run($request->user()->id, $window));
    })->name('dashboard.impending-drains');

    Route::middleware('ownership')->group(function () {
        Route::resource('balances', BalanceController::class)->only(['index', 'show', 'store', 'update', 'destroy']);
        Route::post('balances/{balance}/set-primary', [BalanceController::class, 'setPrimary'])->name('balances.set-primary');
        Route::post('balances/{balance}/reconcile', [BalanceController::class, 'reconcile'])->name('balances.reconcile');

        Route::resource('categories', CategoryController::class)->only(['index', 'store', 'update', 'destroy']);

        Route::resource('budgets', BudgetController::class);
        Route::post('budgets/{budget}/set-active', [BudgetController::class, 'setActive'])->name('budgets.set-active');

        // Web (session-auth) mirror of the /api/transactions read for
        // BudgetDetail — /api/* is Sanctum token-only since the 2026-08-12
        // lockdown, so browser sessions use this route instead. Uses the
        // envelope-aware wrapper (fund.reserved + payout exclusion); the
        // frozen /api/transactions contract keeps GetTransactionApiController.
        Route::get('budgets/{budget}/transactions', BudgetTransactionsController::class)
            ->name('budgets.transactions');

        Route::get('transactions/export', [TransactionController::class, 'export'])->name('transactions.export');
        Route::resource('transactions', TransactionController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::post('transactions/bulk-store', [TransactionController::class, 'bulkStore'])->name('transactions.bulk-store');
        Route::post('transactions/transfer-between-accounts', [TransactionController::class, 'transferBetweenAccounts'])->name('transactions.transfer-between-accounts');

        Route::resource('recurring-transactions', RecurringTransactionController::class)->only(['index', 'store', 'update', 'destroy']);

        Route::resource('funds', FundController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::post('funds/{fund}/contributions', [FundController::class, 'storeContribution'])->name('funds.contributions.store');
        Route::post('funds/{fund}/withdrawals', [FundController::class, 'storeWithdrawal'])->name('funds.withdrawals.store');
        Route::delete('fund-contributions/{contribution}', [FundController::class, 'destroyContribution'])->name('fund-contributions.destroy');
    });

    // OAuth 2.0 Authorization Consent
    Route::get('oauth/authorize', [OAuthAuthorizationController::class, 'authorize'])->name('oauth.authorize');
    Route::post('oauth/authorize', [OAuthAuthorizationController::class, 'approve'])->name('oauth.approve');
});

// RFC 8414 & RFC 9728 Discovery Endpoints (Public)
Route::get('.well-known/oauth-authorization-server', [OAuthDiscoveryController::class, 'authorizationServerMetadata'])
    ->name('oauth.discovery.server');
Route::get('.well-known/oauth-protected-resource', [OAuthDiscoveryController::class, 'protectedResourceMetadata'])
    ->name('oauth.discovery.resource');

// OAuth 2.0 Token Endpoint (Public, authenticated via client credentials)
Route::post('oauth/token', [OAuthTokenController::class, 'token'])->name('oauth.token');

require __DIR__.'/settings.php';
