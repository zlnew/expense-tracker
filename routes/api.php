<?php

use App\Http\Controllers\Api\BalanceApiController;
use App\Http\Controllers\Api\BudgetApiController;
use App\Http\Controllers\Api\CategoryApiController;
use App\Http\Controllers\Api\FundApiController;
use App\Http\Controllers\Api\GetTransactionApiController;
use App\Http\Controllers\Api\StoreTransactionApiController;
use App\Http\Controllers\Api\UpcomingFundApiController;
use App\Http\Controllers\Api\UpdateTransactionApiController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('transactions', GetTransactionApiController::class)
        ->middleware('abilities:transactions:read')
        ->name('api.transactions');

    Route::post('transactions', StoreTransactionApiController::class)
        ->middleware('abilities:transactions:write')
        ->name('api.transactions.store');

    Route::patch('transactions/{transaction}', UpdateTransactionApiController::class)
        ->middleware('abilities:transactions:write')
        ->name('api.transactions.update');

    Route::get('categories', CategoryApiController::class)
        ->middleware('abilities:categories:read')
        ->name('api.categories');

    Route::get('balances', BalanceApiController::class)
        ->middleware('abilities:balances:read')
        ->name('api.balances');

    Route::get('budgets', BudgetApiController::class)
        ->middleware('abilities:budgets:read')
        ->name('api.budgets');

    // Static segment must be declared BEFORE the {fund} wildcard.
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
});
