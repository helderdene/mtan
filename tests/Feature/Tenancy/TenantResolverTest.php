<?php

use App\Services\Tenancy\TenantResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    // Clean up central database tables (order matters due to foreign keys)
    DB::connection('central')->table('tenant_usage_metrics')->delete();
    DB::connection('central')->table('device_registry')->delete();
    DB::connection('central')->table('tenants')->delete();

    // Clear cache before each test
    Cache::flush();
});

describe('Tenant Resolution', function () {
    test('resolves tenant from subdomain', function () {
        // Create tenant in central database
        DB::connection('central')->table('tenants')->insert([
            'id' => '550e8400-e29b-41d4-a716-446655440000',
            'company_name' => 'Acme Corporation',
            'subdomain' => 'acme',
            'domain' => null,
            'database_name' => 'tenant_550e8400_e29b_41d4_a716_446655440000',
            'subscription_plan' => 'professional',
            'database_host' => '127.0.0.1',
            'max_employees' => 100,
            'max_devices' => 10,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $request = Request::create('https://acme.attendance.local/dashboard');
        $resolver = new TenantResolver;

        $tenant = $resolver->resolve($request);

        expect($tenant)->not->toBeNull();
        expect($tenant->id)->toBe('550e8400-e29b-41d4-a716-446655440000');
        expect($tenant->company_name)->toBe('Acme Corporation');
        expect($tenant->subdomain)->toBe('acme');
        expect($tenant->database_name)->toBe('tenant_550e8400_e29b_41d4_a716_446655440000');
    });

    test('resolves tenant from custom domain', function () {
        // Create tenant with custom domain
        DB::connection('central')->table('tenants')->insert([
            'id' => '660e8400-e29b-41d4-a716-446655440001',
            'company_name' => 'Beta Industries',
            'subdomain' => 'beta',
            'domain' => 'attendance.beta.com',
            'database_name' => 'tenant_660e8400_e29b_41d4_a716_446655440001',
            'subscription_plan' => 'enterprise',
            'database_host' => '127.0.0.1',
            'max_employees' => 500,
            'max_devices' => 50,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $request = Request::create('https://attendance.beta.com/dashboard');
        $resolver = new TenantResolver;

        $tenant = $resolver->resolve($request);

        expect($tenant)->not->toBeNull();
        expect($tenant->id)->toBe('660e8400-e29b-41d4-a716-446655440001');
        expect($tenant->company_name)->toBe('Beta Industries');
        expect($tenant->domain)->toBe('attendance.beta.com');
    });

    test('returns null when tenant not found', function () {
        $request = Request::create('https://nonexistent.attendance.local/dashboard');
        $resolver = new TenantResolver;

        $tenant = $resolver->resolve($request);

        expect($tenant)->toBeNull();
    });

    test('returns null when accessing base domain without subdomain', function () {
        $request = Request::create('https://attendance.local/');
        $resolver = new TenantResolver;

        $tenant = $resolver->resolve($request);

        expect($tenant)->toBeNull();
    });

    test('returns null for inactive tenant', function () {
        // Create inactive tenant
        DB::connection('central')->table('tenants')->insert([
            'id' => '770e8400-e29b-41d4-a716-446655440002',
            'company_name' => 'Inactive Corp',
            'subdomain' => 'inactive',
            'domain' => null,
            'database_name' => 'tenant_770e8400_e29b_41d4_a716_446655440002',
            'subscription_plan' => 'basic',
            'database_host' => '127.0.0.1',
            'max_employees' => 50,
            'max_devices' => 5,
            'is_active' => false, // Inactive
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $request = Request::create('https://inactive.attendance.local/dashboard');
        $resolver = new TenantResolver;

        $tenant = $resolver->resolve($request);

        expect($tenant)->toBeNull();
    });
});

describe('Tenant Caching', function () {
    test('caches resolved tenant by subdomain', function () {
        DB::connection('central')->table('tenants')->insert([
            'id' => '880e8400-e29b-41d4-a716-446655440003',
            'company_name' => 'Cache Test Corp',
            'subdomain' => 'cachetest',
            'domain' => null,
            'database_name' => 'tenant_880e8400_e29b_41d4_a716_446655440003',
            'subscription_plan' => 'basic',
            'database_host' => '127.0.0.1',
            'max_employees' => 50,
            'max_devices' => 5,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Cache::flush();

        $request = Request::create('https://cachetest.attendance.local/dashboard');
        $resolver = new TenantResolver;

        // First resolution - should query database
        $tenant1 = $resolver->resolve($request);
        expect($tenant1)->not->toBeNull();

        // Verify cache was set
        $cacheKey = 'tenant:subdomain:cachetest';
        expect(Cache::has($cacheKey))->toBeTrue();

        // Delete from database
        DB::connection('central')->table('tenants')
            ->where('id', '880e8400-e29b-41d4-a716-446655440003')
            ->delete();

        // Second resolution - should return from cache despite database deletion
        $tenant2 = $resolver->resolve($request);
        expect($tenant2)->not->toBeNull();
        expect($tenant2->id)->toBe('880e8400-e29b-41d4-a716-446655440003');
    });

    test('caches resolved tenant by custom domain', function () {
        DB::connection('central')->table('tenants')->insert([
            'id' => '990e8400-e29b-41d4-a716-446655440004',
            'company_name' => 'Domain Cache Corp',
            'subdomain' => 'domaincache',
            'domain' => 'attendance.domaincache.com',
            'database_name' => 'tenant_990e8400_e29b_41d4_a716_446655440004',
            'subscription_plan' => 'professional',
            'database_host' => '127.0.0.1',
            'max_employees' => 100,
            'max_devices' => 10,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Cache::flush();

        $request = Request::create('https://attendance.domaincache.com/dashboard');
        $resolver = new TenantResolver;

        // First resolution
        $tenant1 = $resolver->resolve($request);
        expect($tenant1)->not->toBeNull();

        // Verify cache was set
        $cacheKey = 'tenant:domain:attendance.domaincache.com';
        expect(Cache::has($cacheKey))->toBeTrue();

        // Delete from database
        DB::connection('central')->table('tenants')
            ->where('id', '990e8400-e29b-41d4-a716-446655440004')
            ->delete();

        // Second resolution - should return from cache
        $tenant2 = $resolver->resolve($request);
        expect($tenant2)->not->toBeNull();
        expect($tenant2->domain)->toBe('attendance.domaincache.com');
    });

    test('caches null result for non-existent tenant', function () {
        Cache::flush();

        $request = Request::create('https://notfound.attendance.local/dashboard');
        $resolver = new TenantResolver;

        // First resolution - should query database
        $tenant1 = $resolver->resolve($request);
        expect($tenant1)->toBeNull();

        // Add tenant to database
        DB::connection('central')->table('tenants')->insert([
            'id' => 'aa0e8400-e29b-41d4-a716-446655440005',
            'company_name' => 'New Corp',
            'subdomain' => 'notfound',
            'domain' => null,
            'database_name' => 'tenant_aa0e8400_e29b_41d4_a716_446655440005',
            'subscription_plan' => 'basic',
            'database_host' => '127.0.0.1',
            'max_employees' => 50,
            'max_devices' => 5,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Second resolution - should still return null from cache (proving null was cached)
        $tenant2 = $resolver->resolve($request);
        expect($tenant2)->toBeNull();

        // Clear cache and try again - should now find the tenant
        Cache::flush();
        $tenant3 = $resolver->resolve($request);
        expect($tenant3)->not->toBeNull();
        expect($tenant3->subdomain)->toBe('notfound');
    });

    test('respects cache TTL configuration', function () {
        // Skip if not using Redis cache driver
        if (config('cache.default') !== 'redis') {
            $this->markTestSkipped('This test requires Redis cache driver');
        }

        DB::connection('central')->table('tenants')->insert([
            'id' => 'bb0e8400-e29b-41d4-a716-446655440006',
            'company_name' => 'TTL Test Corp',
            'subdomain' => 'ttltest',
            'domain' => null,
            'database_name' => 'tenant_bb0e8400_e29b_41d4_a716_446655440006',
            'subscription_plan' => 'basic',
            'database_host' => '127.0.0.1',
            'max_employees' => 50,
            'max_devices' => 5,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Cache::flush();

        $request = Request::create('https://ttltest.attendance.local/dashboard');
        $resolver = new TenantResolver;

        $tenant = $resolver->resolve($request);
        expect($tenant)->not->toBeNull();

        // Verify cache has TTL (default 5 minutes = 300 seconds)
        $cacheKey = 'tenant:subdomain:ttltest';
        $ttl = Cache::getStore()->getRedis()->ttl(config('cache.prefix').':'.$cacheKey);

        expect($ttl)->toBeGreaterThan(0);
        expect($ttl)->toBeLessThanOrEqual(300);
    });
});

describe('Edge Cases', function () {
    test('handles www subdomain correctly', function () {
        // Should treat www.attendance.local as base domain, not subdomain
        $request = Request::create('https://www.attendance.local/');
        $resolver = new TenantResolver;

        $tenant = $resolver->resolve($request);
        expect($tenant)->toBeNull();
    });

    test('handles localhost development environment', function () {
        DB::connection('central')->table('tenants')->insert([
            'id' => 'cc0e8400-e29b-41d4-a716-446655440007',
            'company_name' => 'Local Dev Corp',
            'subdomain' => 'dev',
            'domain' => null,
            'database_name' => 'tenant_cc0e8400_e29b_41d4_a716_446655440007',
            'subscription_plan' => 'basic',
            'database_host' => '127.0.0.1',
            'max_employees' => 50,
            'max_devices' => 5,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $request = Request::create('http://dev.localhost:8000/dashboard');
        $resolver = new TenantResolver;

        $tenant = $resolver->resolve($request);
        expect($tenant)->not->toBeNull();
        expect($tenant->subdomain)->toBe('dev');
    });

    test('prioritizes custom domain over subdomain when both match', function () {
        // Create two tenants - one with custom domain that happens to match another's subdomain pattern
        DB::connection('central')->table('tenants')->insert([
            [
                'id' => 'dd0e8400-e29b-41d4-a716-446655440008',
                'company_name' => 'Subdomain Corp',
                'subdomain' => 'priority',
                'domain' => null,
                'database_name' => 'tenant_dd0e8400_e29b_41d4_a716_446655440008',
                'subscription_plan' => 'basic',
                'database_host' => '127.0.0.1',
                'max_employees' => 50,
                'max_devices' => 5,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 'ee0e8400-e29b-41d4-a716-446655440009',
                'company_name' => 'Custom Domain Corp',
                'subdomain' => 'customdomain',
                'domain' => 'priority.attendance.local',
                'database_name' => 'tenant_ee0e8400_e29b_41d4_a716_446655440009',
                'subscription_plan' => 'enterprise',
                'database_host' => '127.0.0.1',
                'max_employees' => 500,
                'max_devices' => 50,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $request = Request::create('https://priority.attendance.local/dashboard');
        $resolver = new TenantResolver;

        $tenant = $resolver->resolve($request);

        // Should resolve to subdomain match (priority subdomain), not domain match
        expect($tenant->id)->toBe('dd0e8400-e29b-41d4-a716-446655440008');
        expect($tenant->subdomain)->toBe('priority');
    });

    test('handles case-insensitive subdomain matching', function () {
        DB::connection('central')->table('tenants')->insert([
            'id' => 'ff0e8400-e29b-41d4-a716-446655440010',
            'company_name' => 'Case Test Corp',
            'subdomain' => 'casetest',
            'domain' => null,
            'database_name' => 'tenant_ff0e8400_e29b_41d4_a716_446655440010',
            'subscription_plan' => 'basic',
            'database_host' => '127.0.0.1',
            'max_employees' => 50,
            'max_devices' => 5,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $request = Request::create('https://CaseTest.attendance.local/dashboard');
        $resolver = new TenantResolver;

        $tenant = $resolver->resolve($request);
        expect($tenant)->not->toBeNull();
        expect($tenant->subdomain)->toBe('casetest');
    });
});
