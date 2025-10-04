<?php

namespace App\Providers;

use App\Http\Middleware\TenantMiddleware;
use App\Services\Tenancy\TenantContext;
use Illuminate\Support\ServiceProvider;

/**
 * TenancyServiceProvider
 *
 * Registers tenancy-related services and middleware
 */
class TenancyServiceProvider extends ServiceProvider
{
    /**
     * Register services
     */
    public function register(): void
    {
        // Register TenantContext as a singleton
        // This ensures the same instance is used throughout a request
        $this->app->singleton(TenantContext::class);
    }

    /**
     * Bootstrap services
     */
    public function boot(): void
    {
        // Register tenant middleware alias
        $this->app['router']->aliasMiddleware('tenant', TenantMiddleware::class);
    }
}
