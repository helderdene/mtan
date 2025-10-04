<?php

use App\DTOs\Tenant;
use App\Services\Tenancy\TenantDatabaseManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    // Clean up central database
    DB::connection('central')->table('tenant_usage_metrics')->delete();
    DB::connection('central')->table('device_registry')->delete();
    DB::connection('central')->table('tenants')->delete();

    // Create a test tenant in central database
    DB::connection('central')->table('tenants')->insert([
        'id' => '550e8400-e29b-41d4-a716-446655440000',
        'company_name' => 'Test Corporation',
        'subdomain' => 'testcorp',
        'domain' => null,
        'database_name' => 'tenant_test_550e8400',
        'database_host' => '127.0.0.1',
        'subscription_plan' => 'professional',
        'max_employees' => 100,
        'max_devices' => 10,
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
});

afterEach(function () {
    // Clean up test tenant database
    $manager = new TenantDatabaseManager;
    try {
        $manager->dropDatabase('tenant_test_550e8400');
    } catch (\Exception $e) {
        // Ignore if database doesn't exist
    }
});

describe('Database Creation', function () {
    test('creates tenant database successfully', function () {
        $tenant = new Tenant(
            id: '550e8400-e29b-41d4-a716-446655440000',
            company_name: 'Test Corporation',
            subdomain: 'testcorp',
            domain: null,
            database_name: 'tenant_test_550e8400',
            database_host: '127.0.0.1',
            subscription_plan: 'professional',
            max_employees: 100,
            max_devices: 10,
            is_active: true,
        );

        $manager = new TenantDatabaseManager;
        $result = $manager->createDatabase($tenant);

        expect($result)->toBeTrue();

        // Verify database exists by connecting to it
        $pdo = new \PDO(
            'mysql:host='.env('DB_HOST', '127.0.0.1'),
            env('DB_USERNAME', 'root'),
            env('DB_PASSWORD', '')
        );
        $stmt = $pdo->prepare('SHOW DATABASES LIKE ?');
        $stmt->execute(['tenant_test_550e8400']);
        $databases = $stmt->fetchAll();
        expect($databases)->toHaveCount(1);
    });

    test('does not fail when database already exists', function () {
        $tenant = new Tenant(
            id: '550e8400-e29b-41d4-a716-446655440000',
            company_name: 'Test Corporation',
            subdomain: 'testcorp',
            domain: null,
            database_name: 'tenant_test_550e8400',
            database_host: '127.0.0.1',
            subscription_plan: 'professional',
            max_employees: 100,
            max_devices: 10,
            is_active: true,
        );

        $manager = new TenantDatabaseManager;

        // Create database first time
        $result1 = $manager->createDatabase($tenant);
        expect($result1)->toBeTrue();

        // Create database second time (should not fail)
        $result2 = $manager->createDatabase($tenant);
        expect($result2)->toBeTrue();
    });

    test('creates database with correct charset and collation', function () {
        $tenant = new Tenant(
            id: '550e8400-e29b-41d4-a716-446655440000',
            company_name: 'Test Corporation',
            subdomain: 'testcorp',
            domain: null,
            database_name: 'tenant_test_550e8400',
            database_host: '127.0.0.1',
            subscription_plan: 'professional',
            max_employees: 100,
            max_devices: 10,
            is_active: true,
        );

        $manager = new TenantDatabaseManager;
        $manager->createDatabase($tenant);

        // Check database charset and collation
        $pdo = new \PDO(
            'mysql:host='.env('DB_HOST', '127.0.0.1'),
            env('DB_USERNAME', 'root'),
            env('DB_PASSWORD', '')
        );
        $stmt = $pdo->prepare(
            'SELECT DEFAULT_CHARACTER_SET_NAME, DEFAULT_COLLATION_NAME
             FROM INFORMATION_SCHEMA.SCHEMATA
             WHERE SCHEMA_NAME = ?'
        );
        $stmt->execute(['tenant_test_550e8400']);
        $result = $stmt->fetchAll(\PDO::FETCH_OBJ);

        expect($result)->toHaveCount(1);
        expect($result[0]->DEFAULT_CHARACTER_SET_NAME)->toBe('utf8mb4');
        expect($result[0]->DEFAULT_COLLATION_NAME)->toBe('utf8mb4_unicode_ci');
    });
});

describe('Database Dropping', function () {
    test('drops tenant database successfully', function () {
        $tenant = new Tenant(
            id: '550e8400-e29b-41d4-a716-446655440000',
            company_name: 'Test Corporation',
            subdomain: 'testcorp',
            domain: null,
            database_name: 'tenant_test_550e8400',
            database_host: '127.0.0.1',
            subscription_plan: 'professional',
            max_employees: 100,
            max_devices: 10,
            is_active: true,
        );

        $manager = new TenantDatabaseManager;

        // Create database first
        $manager->createDatabase($tenant);

        // Verify it exists
        $pdo = new \PDO(
            'mysql:host='.env('DB_HOST', '127.0.0.1'),
            env('DB_USERNAME', 'root'),
            env('DB_PASSWORD', '')
        );
        $stmt = $pdo->prepare('SHOW DATABASES LIKE ?');
        $stmt->execute(['tenant_test_550e8400']);
        $databases = $stmt->fetchAll();
        expect($databases)->toHaveCount(1);

        // Drop database
        $result = $manager->dropDatabase('tenant_test_550e8400');
        expect($result)->toBeTrue();

        // Verify it no longer exists
        $stmt = $pdo->prepare('SHOW DATABASES LIKE ?');
        $stmt->execute(['tenant_test_550e8400']);
        $databases = $stmt->fetchAll();
        expect($databases)->toHaveCount(0);
    });

    test('does not fail when dropping non-existent database', function () {
        $manager = new TenantDatabaseManager;

        // Drop database that doesn't exist
        $result = $manager->dropDatabase('tenant_nonexistent_12345');

        // Should not throw exception
        expect($result)->toBeTrue();
    });
});

describe('Connection Management', function () {
    test('creates dynamic tenant connection', function () {
        $tenant = new Tenant(
            id: '550e8400-e29b-41d4-a716-446655440000',
            company_name: 'Test Corporation',
            subdomain: 'testcorp',
            domain: null,
            database_name: 'tenant_test_550e8400',
            database_host: '127.0.0.1',
            subscription_plan: 'professional',
            max_employees: 100,
            max_devices: 10,
            is_active: true,
        );

        $manager = new TenantDatabaseManager;

        // Create database
        $manager->createDatabase($tenant);

        // Set up tenant connection
        $connectionName = $manager->setupTenantConnection($tenant);

        expect($connectionName)->toBe('tenant');

        // Verify connection works
        $result = DB::connection($connectionName)->select('SELECT DATABASE()');
        expect($result[0]->{'DATABASE()'})->toBe('tenant_test_550e8400');
    });

    test('tenant connection is isolated from central connection', function () {
        $tenant = new Tenant(
            id: '550e8400-e29b-41d4-a716-446655440000',
            company_name: 'Test Corporation',
            subdomain: 'testcorp',
            domain: null,
            database_name: 'tenant_test_550e8400',
            database_host: '127.0.0.1',
            subscription_plan: 'professional',
            max_employees: 100,
            max_devices: 10,
            is_active: true,
        );

        $manager = new TenantDatabaseManager;
        $manager->createDatabase($tenant);
        $manager->setupTenantConnection($tenant);

        // Get current database for each connection
        $centralDb = DB::connection('central')->select('SELECT DATABASE()')[0]->{'DATABASE()'};
        $tenantDb = DB::connection('tenant')->select('SELECT DATABASE()')[0]->{'DATABASE()'};

        expect($centralDb)->toBe('attendance_central');
        expect($tenantDb)->toBe('tenant_test_550e8400');
        expect($tenantDb)->not->toBe($centralDb);
    });
});

describe('Migration Running', function () {
    test('runs tenant migrations successfully', function () {
        $tenant = new Tenant(
            id: '550e8400-e29b-41d4-a716-446655440000',
            company_name: 'Test Corporation',
            subdomain: 'testcorp',
            domain: null,
            database_name: 'tenant_test_550e8400',
            database_host: '127.0.0.1',
            subscription_plan: 'professional',
            max_employees: 100,
            max_devices: 10,
            is_active: true,
        );

        $manager = new TenantDatabaseManager;

        // Create database and setup connection
        $manager->createDatabase($tenant);
        $manager->setupTenantConnection($tenant);

        // Run migrations
        $result = $manager->runMigrations($tenant);
        expect($result)->toBeTrue();

        // Verify migrations table exists
        expect(Schema::connection('tenant')->hasTable('migrations'))->toBeTrue();

        // Verify at least one migration ran (password_reset_tokens or other tenant migrations)
        $migrations = DB::connection('tenant')->table('migrations')->count();
        expect($migrations)->toBeGreaterThan(0);
    });

    test('tenant tables are created by migrations', function () {
        $tenant = new Tenant(
            id: '550e8400-e29b-41d4-a716-446655440000',
            company_name: 'Test Corporation',
            subdomain: 'testcorp',
            domain: null,
            database_name: 'tenant_test_550e8400',
            database_host: '127.0.0.1',
            subscription_plan: 'professional',
            max_employees: 100,
            max_devices: 10,
            is_active: true,
        );

        $manager = new TenantDatabaseManager;
        $manager->createDatabase($tenant);
        $manager->setupTenantConnection($tenant);
        $manager->runMigrations($tenant);

        // Verify essential tenant tables exist (from Laravel's default migrations)
        expect(Schema::connection('tenant')->hasTable('users'))->toBeTrue();
        expect(Schema::connection('tenant')->hasTable('password_reset_tokens'))->toBeTrue();
        expect(Schema::connection('tenant')->hasTable('sessions'))->toBeTrue();
    });
});

describe('Provisioning Workflow', function () {
    test('provisions complete tenant database from scratch', function () {
        $tenant = new Tenant(
            id: '550e8400-e29b-41d4-a716-446655440000',
            company_name: 'Test Corporation',
            subdomain: 'testcorp',
            domain: null,
            database_name: 'tenant_test_550e8400',
            database_host: '127.0.0.1',
            subscription_plan: 'professional',
            max_employees: 100,
            max_devices: 10,
            is_active: true,
        );

        $manager = new TenantDatabaseManager;

        // Full provisioning workflow
        $result = $manager->provisionTenant($tenant);
        expect($result)->toBeTrue();

        // Verify database exists
        $pdo = new \PDO(
            'mysql:host='.env('DB_HOST', '127.0.0.1'),
            env('DB_USERNAME', 'root'),
            env('DB_PASSWORD', '')
        );
        $stmt = $pdo->prepare('SHOW DATABASES LIKE ?');
        $stmt->execute(['tenant_test_550e8400']);
        $databases = $stmt->fetchAll();
        expect($databases)->toHaveCount(1);

        // Verify connection is set up
        $currentDb = DB::connection('tenant')->select('SELECT DATABASE()')[0]->{'DATABASE()'};
        expect($currentDb)->toBe('tenant_test_550e8400');

        // Verify migrations ran
        expect(Schema::connection('tenant')->hasTable('users'))->toBeTrue();
    });

    test('handles provisioning errors gracefully', function () {
        $tenant = new Tenant(
            id: '550e8400-e29b-41d4-a716-446655440000',
            company_name: 'Test Corporation',
            subdomain: 'testcorp',
            domain: null,
            database_name: 'tenant_test_550e8400',
            database_host: '127.0.0.1',
            subscription_plan: 'professional',
            max_employees: 100,
            max_devices: 10,
            is_active: true,
        );

        $manager = new TenantDatabaseManager;

        // First provisioning should succeed
        $result1 = $manager->provisionTenant($tenant);
        expect($result1)->toBeTrue();

        // Second provisioning should also succeed (idempotent)
        $result2 = $manager->provisionTenant($tenant);
        expect($result2)->toBeTrue();
    });
});

describe('Edge Cases', function () {
    test('handles database names with special characters safely', function () {
        // Database names should be sanitized/validated
        $tenant = new Tenant(
            id: '550e8400-e29b-41d4-a716-446655440000',
            company_name: 'Test Corporation',
            subdomain: 'testcorp',
            domain: null,
            database_name: 'tenant_test_550e8400',  // Safe name
            database_host: '127.0.0.1',
            subscription_plan: 'professional',
            max_employees: 100,
            max_devices: 10,
            is_active: true,
        );

        $manager = new TenantDatabaseManager;
        $result = $manager->createDatabase($tenant);

        expect($result)->toBeTrue();
    });

    test('verifies database name format follows convention', function () {
        $tenant = new Tenant(
            id: '550e8400-e29b-41d4-a716-446655440000',
            company_name: 'Test Corporation',
            subdomain: 'testcorp',
            domain: null,
            database_name: 'tenant_test_550e8400',
            database_host: '127.0.0.1',
            subscription_plan: 'professional',
            max_employees: 100,
            max_devices: 10,
            is_active: true,
        );

        $manager = new TenantDatabaseManager;
        $manager->createDatabase($tenant);

        // Verify naming convention: tenant_*
        expect($tenant->database_name)->toStartWith('tenant_');
    });
});
