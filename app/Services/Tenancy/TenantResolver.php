<?php

namespace App\Services\Tenancy;

use App\DTOs\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * TenantResolver
 *
 * Resolves the current tenant from the HTTP request by:
 * 1. Extracting subdomain (e.g., acme.attendance.local -> acme)
 * 2. Extracting custom domain (e.g., attendance.acme.com)
 * 3. Querying central database with caching
 * 4. Returning null if tenant not found or inactive
 */
class TenantResolver
{
    /**
     * Cache TTL in seconds (5 minutes)
     */
    private const CACHE_TTL = 300;

    /**
     * Marker value for cached "not found" results
     */
    private const NOT_FOUND_MARKER = '__TENANT_NOT_FOUND__';

    /**
     * Base domains for subdomain extraction
     */
    private const BASE_DOMAINS = [
        'attendance.local',
        'localhost',
    ];

    /**
     * Resolve tenant from HTTP request
     */
    public function resolve(Request $request): ?Tenant
    {
        $host = $request->getHost();

        // Try subdomain resolution first (e.g., acme.attendance.local)
        $subdomain = $this->extractSubdomain($host);
        if ($subdomain) {
            $tenant = $this->resolveBySubdomain($subdomain);
            if ($tenant) {
                return $tenant;
            }
        }

        // Fallback to custom domain resolution (e.g., attendance.acme.com)
        return $this->resolveByDomain($host);
    }

    /**
     * Resolve tenant by subdomain with caching
     */
    private function resolveBySubdomain(string $subdomain): ?Tenant
    {
        $cacheKey = "tenant:subdomain:{$subdomain}";

        // Check cache (using get with default to detect absence)
        $cached = Cache::get($cacheKey, 'NOT_IN_CACHE');

        if ($cached !== 'NOT_IN_CACHE') {
            // Return null if we cached a "not found" marker
            return $cached === self::NOT_FOUND_MARKER ? null : $cached;
        }

        // Query database
        $row = DB::connection('central')
            ->table('tenants')
            ->where('subdomain', strtolower($subdomain))
            ->where('is_active', true)
            ->first();

        if ($row) {
            $tenant = Tenant::fromDatabase($row);
            Cache::put($cacheKey, $tenant, self::CACHE_TTL);
            return $tenant;
        }

        // Cache "not found" result to avoid repeated database queries
        Cache::put($cacheKey, self::NOT_FOUND_MARKER, self::CACHE_TTL);
        return null;
    }

    /**
     * Resolve tenant by custom domain with caching
     */
    private function resolveByDomain(string $domain): ?Tenant
    {
        $cacheKey = "tenant:domain:{$domain}";

        // Check cache (using get with default to detect absence)
        $cached = Cache::get($cacheKey, 'NOT_IN_CACHE');

        if ($cached !== 'NOT_IN_CACHE') {
            // Return null if we cached a "not found" marker
            return $cached === self::NOT_FOUND_MARKER ? null : $cached;
        }

        // Query database
        $row = DB::connection('central')
            ->table('tenants')
            ->where('domain', $domain)
            ->where('is_active', true)
            ->first();

        if ($row) {
            $tenant = Tenant::fromDatabase($row);
            Cache::put($cacheKey, $tenant, self::CACHE_TTL);
            return $tenant;
        }

        // Cache "not found" result to avoid repeated database queries
        Cache::put($cacheKey, self::NOT_FOUND_MARKER, self::CACHE_TTL);
        return null;
    }

    /**
     * Extract subdomain from host
     *
     * Examples:
     * - acme.attendance.local -> acme
     * - dev.localhost -> dev
     * - attendance.local -> null (base domain)
     * - www.attendance.local -> null (www is ignored)
     * - attendance.acme.com -> null (not a recognized base domain)
     *
     * @return string|null Subdomain or null if not found/base domain
     */
    private function extractSubdomain(string $host): ?string
    {
        // Remove port if present (e.g., localhost:8000 -> localhost)
        $host = explode(':', $host)[0];

        // Check each base domain
        foreach (self::BASE_DOMAINS as $baseDomain) {
            if ($host === $baseDomain) {
                // Exact match to base domain - no subdomain
                return null;
            }

            if (str_ends_with($host, ".{$baseDomain}")) {
                // Extract subdomain (e.g., acme.attendance.local -> acme)
                $subdomain = substr($host, 0, -strlen(".{$baseDomain}"));

                // Ignore www subdomain
                if ($subdomain === 'www') {
                    return null;
                }

                // Handle multi-level subdomains (e.g., app.acme.attendance.local -> app.acme)
                // For Phase 1, we only support single-level subdomains
                if (str_contains($subdomain, '.')) {
                    return null;
                }

                return strtolower($subdomain);
            }
        }

        return null;
    }

    /**
     * Clear cached tenant data
     *
     * Useful for invalidating cache when tenant data is updated
     */
    public function clearCache(string $subdomain = null, string $domain = null): void
    {
        if ($subdomain) {
            Cache::forget("tenant:subdomain:{$subdomain}");
        }

        if ($domain) {
            Cache::forget("tenant:domain:{$domain}");
        }
    }
}
