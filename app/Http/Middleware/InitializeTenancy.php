<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class InitializeTenancy
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Skip for admin subdomain
        if ($request->attributes->get('is_admin_subdomain')) {
            return $next($request);
        }

        // Get subdomain from request attributes (set by SubdomainDetectionMiddleware)
        $subdomain = $request->attributes->get('subdomain');

        if (!$subdomain) {
            // No subdomain, use main domain - don't initialize tenancy
            return $next($request);
        }

        // Look up tenant by subdomain in central database
        $tenant = Tenant::on('central')->where('subdomain', $subdomain)->first();

        if (!$tenant) {
            abort(404, 'Tenant not found');
        }

        if (!$tenant->is_active) {
            abort(403, 'This tenant account has been deactivated');
        }

        // Configure tenant database connection
        Config::set('database.connections.tenant', [
            'driver' => 'mysql',
            'host' => $tenant->database_host,
            'database' => $tenant->database_name,
            'username' => env('DB_USERNAME'),
            'password' => env('DB_PASSWORD'),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'strict' => true,
            'engine' => null,
        ]);

        // Purge the connection to ensure fresh connection
        DB::purge('tenant');

        // Configure auth to use tenant user provider
        Config::set('auth.guards.web.provider', 'tenant_users');
        Config::set('auth.providers.tenant_users', [
            'driver' => 'tenant-eloquent',
            'model' => \App\Models\User::class,
        ]);

        // Store tenant in request for later use
        $request->attributes->set('tenant', $tenant);

        return $next($request);
    }
}
