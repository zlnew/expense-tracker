<?php

use App\Http\Controllers\BalanceController;
use App\Http\Controllers\BudgetController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FundController;
use App\Http\Controllers\RecurringTransactionController;
use App\Http\Controllers\TransactionController;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

Route::inertia('/', 'Welcome', [
    'canRegister' => Features::enabled(Features::registration()),
])->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    Route::middleware('ownership')->group(function () {
        Route::resource('balances', BalanceController::class)->only(['index', 'show', 'store', 'update', 'destroy']);
        Route::post('balances/{balance}/set-primary', [BalanceController::class, 'setPrimary'])->name('balances.set-primary');

        Route::resource('categories', CategoryController::class)->only(['index', 'store', 'update', 'destroy']);

        Route::resource('budgets', BudgetController::class);
        Route::post('budgets/{budget}/set-active', [BudgetController::class, 'setActive'])->name('budgets.set-active');

        Route::get('transactions/export', [TransactionController::class, 'export'])->name('transactions.export');
        Route::resource('transactions', TransactionController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::post('transactions/bulk-store', [TransactionController::class, 'bulkStore'])->name('transactions.bulk-store');
        Route::post('transactions/transfer-between-accounts', [TransactionController::class, 'transferBetweenAccounts'])->name('transactions.transfer-between-accounts');

        Route::resource('recurring-transactions', RecurringTransactionController::class)->only(['index', 'store', 'update', 'destroy']);

        Route::resource('funds', FundController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::post('funds/{fund}/contributions', [FundController::class, 'storeContribution'])->name('funds.contributions.store');
        Route::post('funds/{fund}/withdrawals', [FundController::class, 'storeWithdrawal'])->name('funds.withdrawals.store');
    });
});

require __DIR__.'/settings.php';
