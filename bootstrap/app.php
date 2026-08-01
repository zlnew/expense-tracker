<?php

use App\Http\Middleware\EnsureOwnership;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\TrustProxies;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Support\Facades\Schedule;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->alias([
            'ownership' => EnsureOwnership::class,
        ]);

        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
            TrustProxies::class,
        ]);

        $middleware->api(append: [
            TrustProxies::class,
        ]);
    })
    ->withSchedule(function (): void {
        // Create transactions for due recurring schedules (runs hourly so a
        // missed daily tick still catches up on the next app visit).
        Schedule::command('recurring:process')->hourly();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
