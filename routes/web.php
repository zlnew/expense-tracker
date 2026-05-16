<?php

use App\Http\Controllers\BalanceController;
use App\Http\Controllers\BudgetController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\TransactionController;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

Route::inertia('/', 'Welcome', [
    'canRegister' => Features::enabled(Features::registration()),
])->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    Route::resource('balances', BalanceController::class)->only(['index', 'show', 'store', 'update', 'destroy']);
    Route::post('balances/{balance}/set-primary', [BalanceController::class, 'setPrimary'])->name('balances.set-primary');

    Route::resource('categories', CategoryController::class)->only(['index', 'store', 'update', 'destroy']);

    Route::resource('budgets', BudgetController::class);
    Route::post('budgets/{budget}/set-active', [BudgetController::class, 'setActive'])->name('budgets.set-active');

    Route::resource('transactions', TransactionController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::post('transactions/bulk-store', [TransactionController::class, 'bulkStore'])->name('transactions.bulk-store');
    Route::post('transactions/transfer-between-accounts', [TransactionController::class, 'transferBetweenAccounts'])->name('transactions.transfer-between-accounts');
});

require __DIR__.'/settings.php';
