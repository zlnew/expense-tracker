<?php

use App\Http\Controllers\Api\GetTransactionApiController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth.session'])->group(function () {
    Route::get('transactions', GetTransactionApiController::class)->name('api.transactions');
});
