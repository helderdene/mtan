<?php

use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            // Super admin routes MUST be loaded first to take precedence
            // These use domain-based routing (admin.*)
            Route::middleware('web')->group(base_path('routes/super-admin.php'));

            // Load web routes (includes auth.php)
            Route::middleware('web')->group(base_path('routes/web.php'));

            // Load API routes
            Route::prefix('api')
                ->middleware('api')
                ->group(base_path('routes/api.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        // Tenant setup must run before session starts
        $middleware->web(prepend: [
            \App\Http\Middleware\SubdomainDetectionMiddleware::class,
            \App\Http\Middleware\InitializeTenancy::class,
        ]);

        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->alias([
            'super_admin' => \App\Http\Middleware\RequiresSuperAdmin::class,
            'tenant_admin' => \App\Http\Middleware\RequiresTenantAdmin::class,
            'tenant_access' => \App\Http\Middleware\EnsureTenantAccess::class,
            'subdomain.detection' => \App\Http\Middleware\SubdomainDetectionMiddleware::class,
            'auth' => \App\Http\Middleware\Authenticate::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
