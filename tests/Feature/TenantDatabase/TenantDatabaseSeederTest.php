<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    // Create test tenant using TenantDatabaseManager
    $tenant = new \App\DTOs\Tenant(
        id: 'seeder-test-001',
        company_name: 'Test Company',
        subdomain: 'test',
        domain: null,
        database_name: 'tenant_test_seeder',
        database_host: env('DB_HOST', '127.0.0.1'),
        subscription_plan: 'professional',
        max_employees: 100,
        max_devices: 10,
        is_active: true,
    );

    $manager = new \App\Services\Tenancy\TenantDatabaseManager();

    // Provision tenant database (creates DB, runs migrations)
    $manager->provisionTenant($tenant);
});

afterEach(function () {
    // Drop test database
    $pdo = new \PDO(
        'mysql:host=' . env('DB_HOST', '127.0.0.1'),
        env('DB_USERNAME', 'root'),
        env('DB_PASSWORD', '')
    );
    $pdo->exec('DROP DATABASE IF EXISTS tenant_test_seeder');
});

// Helper function to run seeder on tenant connection
function runTenantSeeder() {
    $seeder = new \Database\Seeders\TenantDatabaseSeeder();

    // Temporarily set default connection to tenant
    $originalConnection = DB::getDefaultConnection();
    DB::setDefaultConnection('tenant');

    $seeder->run();

    // Restore original connection
    DB::setDefaultConnection($originalConnection);
}

describe('Tenant Database Seeder', function () {
    test('creates default departments', function () {
        runTenantSeeder();

        // Verify default departments exist
        $departments = DB::connection('tenant')->table('departments')->get();

        expect($departments)->toHaveCount(5);
        expect($departments->pluck('name')->toArray())->toContain('General');
        expect($departments->pluck('name')->toArray())->toContain('Human Resources');
        expect($departments->pluck('name')->toArray())->toContain('IT');
        expect($departments->pluck('name')->toArray())->toContain('Sales');
        expect($departments->pluck('name')->toArray())->toContain('Operations');
    });

    test('creates default shifts', function () {
        runTenantSeeder();

        $shifts = DB::connection('tenant')->table('shifts')->get();

        expect($shifts)->toHaveCount(3);

        // Verify day shift
        $dayShift = $shifts->firstWhere('name', 'Day Shift');
        expect($dayShift)->not->toBeNull();
        expect($dayShift->start_time)->toBe('09:00:00');
        expect($dayShift->end_time)->toBe('17:00:00');
        expect($dayShift->is_default)->toBe(1);

        $workingDays = json_decode($dayShift->working_days);
        expect($workingDays)->toContain('monday');
        expect($workingDays)->toContain('friday');
        expect($workingDays)->not->toContain('saturday');
        expect($workingDays)->not->toContain('sunday');

        // Verify night shift
        $nightShift = $shifts->firstWhere('name', 'Night Shift');
        expect($nightShift)->not->toBeNull();
        expect($nightShift->start_time)->toBe('22:00:00');
        expect($nightShift->end_time)->toBe('06:00:00');
        expect($nightShift->is_default)->toBe(0);

        // Verify flexible shift
        $flexShift = $shifts->firstWhere('name', 'Flexible');
        expect($flexShift)->not->toBeNull();
        expect($flexShift->is_default)->toBe(0);
    });

    test('creates default admin user', function () {
        runTenantSeeder();

        $users = DB::connection('tenant')->table('users')->get();

        expect($users)->toHaveCount(1);

        $admin = $users->first();
        expect($admin->name)->toBe('Admin');
        expect($admin->email)->toBe('admin@tenant.local');
        expect($admin->password)->not->toBeNull();
        expect($admin->email_verified_at)->not->toBeNull();
    });

    test('seeder is idempotent when run multiple times', function () {
        // Run seeder first time
        runTenantSeeder();

        $departmentsCount1 = DB::connection('tenant')->table('departments')->count();
        $shiftsCount1 = DB::connection('tenant')->table('shifts')->count();
        $usersCount1 = DB::connection('tenant')->table('users')->count();

        // Run seeder second time
        runTenantSeeder();

        $departmentsCount2 = DB::connection('tenant')->table('departments')->count();
        $shiftsCount2 = DB::connection('tenant')->table('shifts')->count();
        $usersCount2 = DB::connection('tenant')->table('users')->count();

        // Counts should remain the same (idempotent)
        expect($departmentsCount2)->toBe($departmentsCount1);
        expect($shiftsCount2)->toBe($shiftsCount1);
        expect($usersCount2)->toBe($usersCount1);
    });

    test('admin user password is hashed', function () {
        runTenantSeeder();

        $admin = DB::connection('tenant')->table('users')->first();

        // Password should be bcrypt hashed (starts with $2y$)
        expect($admin->password)->toStartWith('$2y$');
        expect(strlen($admin->password))->toBe(60); // Bcrypt hash length
    });

    test('default shift has correct working days', function () {
        runTenantSeeder();

        $dayShift = DB::connection('tenant')->table('shifts')
            ->where('is_default', true)
            ->first();

        expect($dayShift)->not->toBeNull();

        $workingDays = json_decode($dayShift->working_days, true);

        expect($workingDays)->toBeArray();
        expect($workingDays)->toHaveCount(5); // Monday to Friday
        expect($workingDays)->toBe(['monday', 'tuesday', 'wednesday', 'thursday', 'friday']);
    });
});
