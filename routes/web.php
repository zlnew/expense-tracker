<?php

use App\Http\Controllers\AuthController;
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

    Route::inertia('dashboard', 'Dashboard')->name('dashboard');
});
