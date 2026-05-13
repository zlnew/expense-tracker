<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BalanceController;
use App\Http\Controllers\BudgetController;
use App\Http\Controllers\BudgetItemController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\TransactionController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', function (Request $request) {
    return $request->user()
        ? to_route('dashboard')
        : to_route('login');
})->name('home');

Route::middleware('guest')->group(function () {
    Route::inertia('login', 'auth/Login')->name('login');
    Route::post('login', [AuthController::class, 'login'])->name('auth.login');
});

Route::middleware('auth')->group(function () {
    Route::post('logout', [AuthController::class, 'logout'])->name('auth.logout');

    Route::get('dashboard', DashboardController::class)->name('dashboard');

    Route::resource('balances', BalanceController::class)->only(['index', 'show', 'store', 'update', 'destroy']);
    Route::post('balances/{balance}/set-primary', [BalanceController::class, 'setPrimary'])->name('balances.set-primary');

    Route::resource('categories', CategoryController::class)->only(['index', 'store', 'update', 'destroy']);

    Route::resource('budgets', BudgetController::class)->only(['index', 'show', 'store', 'update', 'destroy']);
    Route::post('budgets/{budget}/set-active', [BudgetController::class, 'setActive'])->name('budgets.set-active');
    Route::post('budgets/{budget}/items', [BudgetItemController::class, 'bulkSave'])->name('budgets.items.bulk-save');

    Route::resource('transactions', TransactionController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::post('transactions/bulk-store', [TransactionController::class, 'bulkStore'])->name('transactions.bulk-store');
});
