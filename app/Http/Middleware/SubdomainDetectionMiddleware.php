<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SubdomainDetectionMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $host = $request->getHost();

        // Extract subdomain from host
        $subdomain = $this->extractSubdomain($host);

        // Determine if this is the admin subdomain
        $isAdminSubdomain = $subdomain === 'admin';

        // Determine subdomain type
        $subdomainType = $this->determineSubdomainType($subdomain, $host);

        // Check if this is a custom domain (no subdomain and not base domain)
        $isCustomDomain = $this->isCustomDomain($subdomain, $host);

        // Set request attributes for later use
        $request->attributes->set('subdomain', $subdomain);
        $request->attributes->set('is_admin_subdomain', $isAdminSubdomain);
        $request->attributes->set('subdomain_type', $subdomainType);
        $request->attributes->set('is_custom_domain', $isCustomDomain);

        return $next($request);
    }

    /**
     * Extract subdomain from host
     */
    protected function extractSubdomain(string $host): ?string
    {
        // Remove port if present
        $host = preg_replace('/:\d+$/', '', $host);

        // Split host into parts
        $parts = explode('.', $host);

        // Special handling for localhost
        if ($parts[count($parts) - 1] === 'localhost') {
            // If only "localhost", no subdomain
            if (count($parts) === 1) {
                return null;
            }
            // If "subdomain.localhost", extract subdomain
            $subdomain = $parts[0];
            if ($subdomain === 'www') {
                return null;
            }
            return $subdomain;
        }

        // If only one or two parts (excluding localhost case), no subdomain
        if (count($parts) <= 2) {
            return null;
        }

        // Get the first part as subdomain
        $subdomain = $parts[0];

        // Treat 'www' as no subdomain
        if ($subdomain === 'www') {
            return null;
        }

        return $subdomain;
    }

    /**
     * Determine the type of subdomain
     */
    protected function determineSubdomainType(?string $subdomain, string $host): ?string
    {
        if ($subdomain === 'admin') {
            return 'admin';
        }

        if ($subdomain !== null) {
            return 'tenant';
        }

        return null;
    }

    /**
     * Check if this is a custom domain (tenant using their own domain)
     */
    protected function isCustomDomain(?string $subdomain, string $host): bool
    {
        // Remove port if present
        $host = preg_replace('/:\d+$/', '', $host);

        // Get configured app domain
        $appDomain = config('app.domain', 'localhost');

        // If no subdomain and host doesn't match app domain, it's a custom domain
        if ($subdomain === null && !str_contains($host, $appDomain)) {
            return true;
        }

        return false;
    }
}
