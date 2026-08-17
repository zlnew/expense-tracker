<?php

use App\Http\Controllers\Api\ApiContractController;
use App\Http\Controllers\Api\BalanceApiController;
use App\Http\Controllers\Api\BudgetApiController;
use App\Http\Controllers\Api\CategoryApiController;
use App\Http\Controllers\Api\FundApiController;
use App\Http\Controllers\Api\RecurringTransactionApiController;
use App\Http\Controllers\Api\TransactionApiController;
use App\Http\Controllers\Api\UpcomingFundApiController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum'])->group(function () {
    // API contract — discoverable endpoints for any valid token. No ability
    // middleware: it filters the route table by the token's own abilities.
    Route::get('contract', ApiContractController::class)
        ->name('api.contract');

    // Transactions — GET is read, everything else is write. Per-method
    // abilities can't ride on a single apiResource (Sanctum's abilities
    // middleware ANDs a comma list), so routes stay explicit with the
    // api.* name prefix — never bare names (they'd collide with the web
    // resource routes and break route()).
    Route::middleware('abilities:transactions:read')
        ->get('transactions', [TransactionApiController::class, 'index'])
        ->name('api.transactions');

    Route::middleware('abilities:transactions:write')
        ->group(function () {
            Route::post('transactions', [TransactionApiController::class, 'store'])
                ->name('api.transactions.store');
            Route::patch('transactions/{transaction}', [TransactionApiController::class, 'update'])
                ->name('api.transactions.update');
            Route::delete('transactions/{transaction}', [TransactionApiController::class, 'destroy'])
                ->name('api.transactions.destroy');
        });

    // Categories
    Route::middleware('abilities:categories:read')
        ->get('categories', [CategoryApiController::class, 'index'])
        ->name('api.categories');

    Route::middleware('abilities:categories:write')
        ->group(function () {
            Route::post('categories', [CategoryApiController::class, 'store'])
                ->name('api.categories.store');
            Route::patch('categories/{category}', [CategoryApiController::class, 'update'])
                ->name('api.categories.update');
            Route::delete('categories/{category}', [CategoryApiController::class, 'destroy'])
                ->name('api.categories.destroy');
        });

    // Balances — show is a read, store/update/destroy are writes.
    Route::middleware('abilities:balances:read')
        ->group(function () {
            Route::get('balances', [BalanceApiController::class, 'index'])
                ->name('api.balances');
            Route::get('balances/{balance}', [BalanceApiController::class, 'show'])
                ->name('api.balances.show');
        });

    Route::middleware('abilities:balances:write')
        ->group(function () {
            Route::post('balances/transfer', [BalanceApiController::class, 'transfer'])
                ->name('api.balances.transfer');
            Route::post('balances', [BalanceApiController::class, 'store'])
                ->name('api.balances.store');
            Route::patch('balances/{balance}', [BalanceApiController::class, 'update'])
                ->name('api.balances.update');
            Route::delete('balances/{balance}', [BalanceApiController::class, 'destroy'])
                ->name('api.balances.destroy');
        });

    // Budgets
    Route::middleware('abilities:budgets:read')
        ->get('budgets', [BudgetApiController::class, 'index'])
        ->name('api.budgets');

    Route::middleware('abilities:budgets:write')
        ->group(function () {
            Route::post('budgets', [BudgetApiController::class, 'store'])
                ->name('api.budgets.store');
            Route::patch('budgets/{budget}', [BudgetApiController::class, 'update'])
                ->name('api.budgets.update');
            Route::delete('budgets/{budget}', [BudgetApiController::class, 'destroy'])
                ->name('api.budgets.destroy');
            Route::post('budgets/{budget}/set-active', [BudgetApiController::class, 'setActive'])
                ->name('api.budgets.set-active');
        });

    // Funds — unchanged. Static segment must be declared BEFORE the {fund} wildcard.
    Route::get('funds/upcoming', UpcomingFundApiController::class)
        ->middleware('abilities:funds:read')
        ->name('api.funds.upcoming');

    Route::get('funds', [FundApiController::class, 'index'])
        ->middleware('abilities:funds:read')
        ->name('api.funds');

    Route::post('funds', [FundApiController::class, 'store'])
        ->middleware('abilities:funds:write')
        ->name('api.funds.store');

    Route::patch('funds/{fund}', [FundApiController::class, 'update'])
        ->middleware('abilities:funds:write')
        ->name('api.funds.update');

    Route::delete('funds/{fund}', [FundApiController::class, 'destroy'])
        ->middleware('abilities:funds:write')
        ->name('api.funds.destroy');

    Route::post('funds/{fund}/contributions', [FundApiController::class, 'storeContribution'])
        ->middleware('abilities:funds:write')
        ->name('api.funds.contributions.store');

    Route::post('funds/{fund}/withdrawals', [FundApiController::class, 'storeWithdrawal'])
        ->middleware('abilities:funds:write')
        ->name('api.funds.withdrawals.store');

    // Recurring transactions — same per-method abilities split as the other
    // resources (a single apiResource can't express read-vs-write).
    Route::middleware('abilities:recurring_transactions:read')
        ->group(function () {
            Route::get('recurring-transactions', [RecurringTransactionApiController::class, 'index'])
                ->name('api.recurring-transactions');
            Route::get('recurring-transactions/{recurring_transaction}', [RecurringTransactionApiController::class, 'show'])
                ->name('api.recurring-transactions.show');
        });

    Route::middleware('abilities:recurring_transactions:write')
        ->group(function () {
            Route::post('recurring-transactions', [RecurringTransactionApiController::class, 'store'])
                ->name('api.recurring-transactions.store');
            Route::patch('recurring-transactions/{recurring_transaction}', [RecurringTransactionApiController::class, 'update'])
                ->name('api.recurring-transactions.update');
            Route::delete('recurring-transactions/{recurring_transaction}', [RecurringTransactionApiController::class, 'destroy'])
                ->name('api.recurring-transactions.destroy');
        });
});
