<?php

use App\Http\Controllers\Api\BalanceApiController;
use App\Http\Controllers\Api\BudgetApiController;
use App\Http\Controllers\Api\CategoryApiController;
use App\Http\Controllers\Api\GetTransactionApiController;
use App\Http\Controllers\Api\StoreTransactionApiController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('transactions', GetTransactionApiController::class)
        ->middleware('abilities:transactions:read')
        ->name('api.transactions');

    Route::post('transactions', StoreTransactionApiController::class)
        ->middleware('abilities:transactions:write')
        ->name('api.transactions.store');

    Route::get('categories', CategoryApiController::class)
        ->middleware('abilities:categories:read')
        ->name('api.categories');

    Route::get('balances', BalanceApiController::class)
        ->middleware('abilities:balances:read')
        ->name('api.balances');

    Route::get('budgets', BudgetApiController::class)
        ->middleware('abilities:budgets:read')
        ->name('api.budgets');
});
