<?php

use App\Http\Middleware\EnsureOwnership;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\HandlePublicCors;
use App\Http\Middleware\TrustProxies;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Middleware\HandleCors;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schedule;
use Laravel\Sanctum\Http\Middleware\CheckAbilities;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        HandleCors::skipWhen(fn (Request $request) => $request->is('api/mcp') || $request->is('oauth/*') || $request->is('.well-known/*')
        );

        $middleware->prepend(HandlePublicCors::class);

        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->alias([
            'ownership' => EnsureOwnership::class,
            'abilities' => CheckAbilities::class,
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

        $middleware->validateCsrfTokens(except: [
            'oauth/token',
            'api/*',
        ]);

        $middleware->throttleApi();
    })
    ->withSchedule(function (): void {
        // Create transactions for due recurring schedules (runs hourly so a
        // missed daily tick still catches up on the next app visit).
        Schedule::command('recurring:process')->hourly();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // API clients (and headless automation) must get JSON error bodies
        // even when they omit the Accept header.
        $exceptions->shouldRenderJsonWhen(fn (Request $request) => $request->is('api/*') || $request->is('oauth/token'));

        $exceptions->respond(function (Response $response, Throwable $e, Request $request) {
            if ($response->getStatusCode() === 401 && $request->is('api/mcp')) {
                $metadataUrl = url('/.well-known/oauth-protected-resource');
                $response->headers->set('WWW-Authenticate', 'Bearer resource_metadata="'.$metadataUrl.'"');
                $response->headers->set('Access-Control-Allow-Origin', '*');
                $response->headers->set('Access-Control-Expose-Headers', 'WWW-Authenticate, Mcp-Session-Id');
            }

            return $response;
        });
    })->create();
