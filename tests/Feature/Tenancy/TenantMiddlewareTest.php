<?php

use App\Http\Middleware\TenantMiddleware;
use App\Services\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    // Clean up central database
    DB::connection('central')->table('tenant_usage_metrics')->delete();
    DB::connection('central')->table('device_registry')->delete();
    DB::connection('central')->table('tenants')->delete();

    // Clear cache
    Cache::flush();

    // Clear tenant context
    app()->forgetInstance(TenantContext::class);
});

describe('Tenant Resolution via Middleware', function () {
    test('resolves tenant from subdomain and sets up connection', function () {
        // Create tenant in central database
        DB::connection('central')->table('tenants')->insert([
            'id' => '550e8400-e29b-41d4-a716-446655440000',
            'company_name' => 'Acme Corporation',
            'subdomain' => 'acme',
            'domain' => null,
            'database_name' => 'tenant_550e8400_e29b_41d4_a716_446655440000',
            'database_host' => '127.0.0.1',
            'subscription_plan' => 'professional',
            'max_employees' => 100,
            'max_devices' => 10,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $request = Request::create('https://acme.attendance.local/dashboard');
        $middleware = app()->make(TenantMiddleware::class);

        $response = $middleware->handle($request, function ($req) {
            // Inside the route handler, tenant should be available
            $context = app(TenantContext::class);
            $tenant = $context->getTenant();

            expect($tenant)->not->toBeNull();
            expect($tenant->id)->toBe('550e8400-e29b-41d4-a716-446655440000');
            expect($tenant->subdomain)->toBe('acme');

            return response('OK');
        });

        expect($response->getContent())->toBe('OK');
    });

    test('resolves tenant from custom domain and sets up connection', function () {
        // Create tenant with custom domain
        DB::connection('central')->table('tenants')->insert([
            'id' => '660e8400-e29b-41d4-a716-446655440001',
            'company_name' => 'Beta Industries',
            'subdomain' => 'beta',
            'domain' => 'attendance.beta.com',
            'database_name' => 'tenant_660e8400_e29b_41d4_a716_446655440001',
            'database_host' => '127.0.0.1',
            'subscription_plan' => 'enterprise',
            'max_employees' => 500,
            'max_devices' => 50,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $request = Request::create('https://attendance.beta.com/dashboard');
        $middleware = app()->make(TenantMiddleware::class);

        $response = $middleware->handle($request, function ($req) {
            $context = app(TenantContext::class);
            $tenant = $context->getTenant();

            expect($tenant)->not->toBeNull();
            expect($tenant->domain)->toBe('attendance.beta.com');

            return response('OK');
        });

        expect($response->getContent())->toBe('OK');
    });

    test('returns 404 when tenant not found', function () {
        $request = Request::create('https://nonexistent.attendance.local/dashboard');
        $middleware = app()->make(TenantMiddleware::class);

        $response = $middleware->handle($request, function ($req) {
            throw new \Exception('Should not reach here');
        });

        expect($response->getStatusCode())->toBe(404);
    });

    test('returns 404 when accessing base domain without tenant', function () {
        $request = Request::create('https://attendance.local/');
        $middleware = app()->make(TenantMiddleware::class);

        $response = $middleware->handle($request, function ($req) {
            throw new \Exception('Should not reach here');
        });

        expect($response->getStatusCode())->toBe(404);
    });

    test('returns 404 when tenant is inactive', function () {
        // Create inactive tenant
        // Note: TenantResolver filters out inactive tenants at the database query level,
        // so they're treated as "not found" rather than "forbidden"
        DB::connection('central')->table('tenants')->insert([
            'id' => '770e8400-e29b-41d4-a716-446655440002',
            'company_name' => 'Inactive Corp',
            'subdomain' => 'inactive',
            'domain' => null,
            'database_name' => 'tenant_770e8400_e29b_41d4_a716_446655440002',
            'database_host' => '127.0.0.1',
            'subscription_plan' => 'basic',
            'max_employees' => 50,
            'max_devices' => 5,
            'is_active' => false, // Inactive
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $request = Request::create('https://inactive.attendance.local/dashboard');
        $middleware = app()->make(TenantMiddleware::class);

        $response = $middleware->handle($request, function ($req) {
            throw new \Exception('Should not reach here');
        });

        // Inactive tenants are filtered at query level, so they return 404 not 403
        expect($response->getStatusCode())->toBe(404);
    });
});

describe('Tenant Context Management', function () {
    test('tenant context is available throughout request lifecycle', function () {
        DB::connection('central')->table('tenants')->insert([
            'id' => '880e8400-e29b-41d4-a716-446655440003',
            'company_name' => 'Context Test Corp',
            'subdomain' => 'contexttest',
            'domain' => null,
            'database_name' => 'tenant_880e8400_e29b_41d4_a716_446655440003',
            'database_host' => '127.0.0.1',
            'subscription_plan' => 'basic',
            'max_employees' => 50,
            'max_devices' => 5,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $request = Request::create('https://contexttest.attendance.local/dashboard');
        $middleware = app()->make(TenantMiddleware::class);

        $middleware->handle($request, function ($req) {
            // Check context is accessible via service container
            $context1 = app(TenantContext::class);
            $context2 = app(TenantContext::class);

            // Same instance (singleton)
            expect($context1)->toBe($context2);

            // Tenant is set
            expect($context1->getTenant())->not->toBeNull();
            expect($context1->getTenant()->subdomain)->toBe('contexttest');

            return response('OK');
        });
    });

    test('tenant context provides tenant ID helper', function () {
        DB::connection('central')->table('tenants')->insert([
            'id' => '990e8400-e29b-41d4-a716-446655440004',
            'company_name' => 'Helper Test Corp',
            'subdomain' => 'helpertest',
            'domain' => null,
            'database_name' => 'tenant_990e8400_e29b_41d4_a716_446655440004',
            'database_host' => '127.0.0.1',
            'subscription_plan' => 'professional',
            'max_employees' => 100,
            'max_devices' => 10,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $request = Request::create('https://helpertest.attendance.local/dashboard');
        $middleware = app()->make(TenantMiddleware::class);

        $middleware->handle($request, function ($req) {
            $context = app(TenantContext::class);

            expect($context->getTenantId())->toBe('990e8400-e29b-41d4-a716-446655440004');
            expect($context->getDatabaseName())->toBe('tenant_990e8400_e29b_41d4_a716_446655440004');

            return response('OK');
        });
    });
});

describe('Database Connection Setup', function () {
    test('tenant database connection is configured after middleware runs', function () {
        // Create tenant and database
        DB::connection('central')->table('tenants')->insert([
            'id' => 'aa0e8400-e29b-41d4-a716-446655440005',
            'company_name' => 'DB Test Corp',
            'subdomain' => 'dbtest',
            'domain' => null,
            'database_name' => 'tenant_test_aa0e8400',
            'database_host' => '127.0.0.1',
            'subscription_plan' => 'basic',
            'max_employees' => 50,
            'max_devices' => 5,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Create the tenant database
        $manager = new \App\Services\Tenancy\TenantDatabaseManager;
        $tenant = new \App\DTOs\Tenant(
            id: 'aa0e8400-e29b-41d4-a716-446655440005',
            company_name: 'DB Test Corp',
            subdomain: 'dbtest',
            domain: null,
            database_name: 'tenant_test_aa0e8400',
            database_host: '127.0.0.1',
            subscription_plan: 'basic',
            max_employees: 50,
            max_devices: 5,
            is_active: true,
        );
        $manager->provisionTenant($tenant);

        $request = Request::create('https://dbtest.attendance.local/dashboard');
        $middleware = app()->make(TenantMiddleware::class);

        $middleware->handle($request, function ($req) {
            // Verify tenant connection points to correct database
            $currentDb = DB::connection('tenant')->select('SELECT DATABASE()')[0]->{'DATABASE()'};
            expect($currentDb)->toBe('tenant_test_aa0e8400');

            return response('OK');
        });

        // Cleanup
        $manager->dropDatabase('tenant_test_aa0e8400');
    });

    test('central database connection remains unchanged', function () {
        DB::connection('central')->table('tenants')->insert([
            'id' => 'bb0e8400-e29b-41d4-a716-446655440006',
            'company_name' => 'Central Test Corp',
            'subdomain' => 'centraltest',
            'domain' => null,
            'database_name' => 'tenant_bb0e8400_e29b_41d4_a716_446655440006',
            'database_host' => '127.0.0.1',
            'subscription_plan' => 'basic',
            'max_employees' => 50,
            'max_devices' => 5,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $request = Request::create('https://centraltest.attendance.local/dashboard');
        $middleware = app()->make(TenantMiddleware::class);

        $middleware->handle($request, function ($req) {
            // Central connection should still point to attendance_central
            $centralDb = DB::connection('central')->select('SELECT DATABASE()')[0]->{'DATABASE()'};
            expect($centralDb)->toBe('attendance_central');

            return response('OK');
        });
    });
});

describe('Edge Cases', function () {
    test('handles multiple middleware executions correctly', function () {
        DB::connection('central')->table('tenants')->insert([
            'id' => 'cc0e8400-e29b-41d4-a716-446655440007',
            'company_name' => 'Multi Test Corp',
            'subdomain' => 'multitest',
            'domain' => null,
            'database_name' => 'tenant_cc0e8400_e29b_41d4_a716_446655440007',
            'database_host' => '127.0.0.1',
            'subscription_plan' => 'basic',
            'max_employees' => 50,
            'max_devices' => 5,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $request = Request::create('https://multitest.attendance.local/dashboard');

        // First execution (simulates first request)
        $middleware1 = app()->make(TenantMiddleware::class);
        $middleware1->handle($request, function ($req) {
            $context = app(TenantContext::class);
            expect($context->getTenant()->subdomain)->toBe('multitest');

            return response('OK1');
        });

        // Second execution (simulate another request with fresh context)
        app()->forgetInstance(TenantContext::class);

        $middleware2 = app()->make(TenantMiddleware::class);
        $middleware2->handle($request, function ($req) {
            $context = app(TenantContext::class);
            expect($context->getTenant()->subdomain)->toBe('multitest');

            return response('OK2');
        });
    });

    test('middleware does not interfere with non-tenant routes', function () {
        // If we have special routes that should bypass tenant resolution
        // (e.g., /health, /metrics), the middleware should handle them gracefully
        $request = Request::create('https://attendance.local/health');
        $middleware = app()->make(TenantMiddleware::class);

        $response = $middleware->handle($request, function ($req) {
            throw new \Exception('Should not reach here for base domain');
        });

        // Should return 404 for base domain (no tenant)
        expect($response->getStatusCode())->toBe(404);
    });
});
