<?php

namespace App\Http\Middleware;

use App\Services\Tenancy\TenantContext;
use App\Services\Tenancy\TenantDatabaseManager;
use App\Services\Tenancy\TenantResolver;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * TenantMiddleware
 *
 * Resolves the current tenant from the request and sets up the tenant database connection.
 * This middleware should be applied to all tenant-specific routes.
 */
class TenantMiddleware
{
    public function __construct(
        private TenantResolver $resolver,
        private TenantDatabaseManager $databaseManager,
        private TenantContext $context
    ) {}

    /**
     * Handle an incoming request
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Resolve tenant from request (subdomain or custom domain)
        $tenant = $this->resolver->resolve($request);

        // Handle tenant not found
        if ($tenant === null) {
            return response()->view('errors.tenant-not-found', [], 404);
        }

        // Handle inactive tenant
        if (! $tenant->is_active) {
            return response()->view('errors.tenant-inactive', [
                'company_name' => $tenant->company_name,
            ], 403);
        }

        // Store tenant in context for use throughout request
        $this->context->setTenant($tenant);

        // Set up tenant database connection
        $this->databaseManager->setupTenantConnection($tenant);

        // Continue to route handler
        return $next($request);
    }
}
