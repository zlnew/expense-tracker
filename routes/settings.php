<?php

use App\Http\Controllers\Settings\OAuthClientController;
use App\Http\Controllers\Settings\PersonalAccessTokenController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\SecurityController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', '/settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('settings/security', [SecurityController::class, 'edit'])->name('security.edit');

    Route::put('settings/password', [SecurityController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('user-password.update');

    Route::inertia('settings/appearance', 'settings/Appearance')->name('appearance.edit');

    Route::get('settings/oauth-clients', [OAuthClientController::class, 'index'])->name('oauth-clients.index');
    Route::post('settings/oauth-clients', [OAuthClientController::class, 'store'])->name('oauth-clients.store');
    Route::delete('settings/oauth-clients/{client}', [OAuthClientController::class, 'destroy'])->name('oauth-clients.destroy');

    Route::get('settings/personal-access-tokens', [PersonalAccessTokenController::class, 'index'])->name('personal-access-tokens.index');
    Route::post('settings/personal-access-tokens', [PersonalAccessTokenController::class, 'store'])->name('personal-access-tokens.store');
    Route::delete('settings/personal-access-tokens/{token}', [PersonalAccessTokenController::class, 'destroy'])->name('personal-access-tokens.destroy');
});
