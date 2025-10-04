<?php

namespace App\Providers;

use App\Auth\TenantUserProvider;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Register custom tenant user provider
        Auth::provider('tenant-eloquent', function ($app, array $config) {
            return new TenantUserProvider($app['hash'], $config['model']);
        });

        // Define authorization gates
        $this->defineAuthorizationGates();
    }

    /**
     * Define authorization gates for role-based access control.
     */
    protected function defineAuthorizationGates(): void
    {
        // Check if user is super admin
        Gate::define('isSuperAdmin', function ($user) {
            return $user->isSuperAdmin();
        });

        // Check if user is tenant admin (or super admin)
        Gate::define('isTenantAdmin', function ($user) {
            return $user->isSuperAdmin() || $user->isTenantAdmin();
        });

        // Check if user can access a specific tenant
        Gate::define('canAccessTenant', function ($user, $tenantId) {
            return $user->isSuperAdmin() || $user->belongsToTenant($tenantId);
        });

        // Check if user can manage employees
        Gate::define('manageEmployees', function ($user) {
            return $user->isSuperAdmin() || $user->isTenantAdmin();
        });

        // Check if user can manage devices
        Gate::define('manageDevices', function ($user) {
            return $user->isSuperAdmin() || $user->isTenantAdmin();
        });

        // Check if user can view reports
        Gate::define('viewReports', function ($user) {
            return $user->isSuperAdmin() || $user->isTenantAdmin();
        });
    }
}
