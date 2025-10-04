<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    // Create a test tenant database
    $pdo = new \PDO(
        'mysql:host=' . env('DB_HOST', '127.0.0.1'),
        env('DB_USERNAME', 'root'),
        env('DB_PASSWORD', '')
    );
    $pdo->exec('CREATE DATABASE IF NOT EXISTS tenant_test_migrations CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');

    // Configure tenant connection
    config()->set('database.connections.tenant', [
        'driver' => 'mysql',
        'host' => env('DB_HOST', '127.0.0.1'),
        'port' => env('DB_PORT', '3306'),
        'database' => 'tenant_test_migrations',
        'username' => env('DB_USERNAME', 'root'),
        'password' => env('DB_PASSWORD', ''),
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
        'prefix' => '',
        'strict' => true,
    ]);

    DB::purge('tenant');
    DB::reconnect('tenant');

    // Run tenant migrations
    Artisan::call('migrate', [
        '--database' => 'tenant',
        '--path' => 'database/migrations/tenant',
        '--force' => true,
    ]);
});

afterEach(function () {
    // Drop test database
    $pdo = new \PDO(
        'mysql:host=' . env('DB_HOST', '127.0.0.1'),
        env('DB_USERNAME', 'root'),
        env('DB_PASSWORD', '')
    );
    $pdo->exec('DROP DATABASE IF EXISTS tenant_test_migrations');
});

describe('Tenant Database Schema', function () {
    test('departments table exists with correct structure', function () {
        expect(Schema::connection('tenant')->hasTable('departments'))->toBeTrue();

        $columns = Schema::connection('tenant')->getColumnListing('departments');
        expect($columns)->toContain('id');
        expect($columns)->toContain('name');
        expect($columns)->toContain('description');
        expect($columns)->toContain('created_at');
        expect($columns)->toContain('updated_at');
    });

    test('employees table exists with correct structure', function () {
        expect(Schema::connection('tenant')->hasTable('employees'))->toBeTrue();

        $columns = Schema::connection('tenant')->getColumnListing('employees');
        expect($columns)->toContain('id');
        expect($columns)->toContain('custom_id');
        expect($columns)->toContain('first_name');
        expect($columns)->toContain('last_name');
        expect($columns)->toContain('email');
        expect($columns)->toContain('phone');
        expect($columns)->toContain('department_id');
        expect($columns)->toContain('is_active');
        expect($columns)->toContain('hired_at');
        expect($columns)->toContain('created_at');
        expect($columns)->toContain('updated_at');
    });

    test('shifts table exists with correct structure', function () {
        expect(Schema::connection('tenant')->hasTable('shifts'))->toBeTrue();

        $columns = Schema::connection('tenant')->getColumnListing('shifts');
        expect($columns)->toContain('id');
        expect($columns)->toContain('name');
        expect($columns)->toContain('start_time');
        expect($columns)->toContain('end_time');
        expect($columns)->toContain('working_days');
        expect($columns)->toContain('is_default');
        expect($columns)->toContain('created_at');
        expect($columns)->toContain('updated_at');
    });

    test('employee_shifts table exists with correct structure', function () {
        expect(Schema::connection('tenant')->hasTable('employee_shifts'))->toBeTrue();

        $columns = Schema::connection('tenant')->getColumnListing('employee_shifts');
        expect($columns)->toContain('id');
        expect($columns)->toContain('employee_id');
        expect($columns)->toContain('shift_id');
        expect($columns)->toContain('effective_from');
        expect($columns)->toContain('effective_to');
        expect($columns)->toContain('created_at');
        expect($columns)->toContain('updated_at');
    });

    test('devices table exists with correct structure', function () {
        expect(Schema::connection('tenant')->hasTable('devices'))->toBeTrue();

        $columns = Schema::connection('tenant')->getColumnListing('devices');
        expect($columns)->toContain('id');
        expect($columns)->toContain('device_id');
        expect($columns)->toContain('name');
        expect($columns)->toContain('location');
        expect($columns)->toContain('ip_address');
        expect($columns)->toContain('capacity');
        expect($columns)->toContain('last_heartbeat_at');
        expect($columns)->toContain('is_active');
        expect($columns)->toContain('created_at');
        expect($columns)->toContain('updated_at');
    });

    test('device_enrollments table exists with correct structure', function () {
        expect(Schema::connection('tenant')->hasTable('device_enrollments'))->toBeTrue();

        $columns = Schema::connection('tenant')->getColumnListing('device_enrollments');
        expect($columns)->toContain('id');
        expect($columns)->toContain('employee_id');
        expect($columns)->toContain('device_id');
        expect($columns)->toContain('enrollment_status');
        expect($columns)->toContain('synced_at');
        expect($columns)->toContain('created_at');
        expect($columns)->toContain('updated_at');
    });

    test('attendance_records table exists with correct structure', function () {
        expect(Schema::connection('tenant')->hasTable('attendance_records'))->toBeTrue();

        $columns = Schema::connection('tenant')->getColumnListing('attendance_records');
        expect($columns)->toContain('id');
        expect($columns)->toContain('employee_id');
        expect($columns)->toContain('device_id');
        expect($columns)->toContain('recorded_at');
        expect($columns)->toContain('direction');
        expect($columns)->toContain('recognition_score');
        expect($columns)->toContain('created_at');
        expect($columns)->toContain('updated_at');
    });

    test('users table exists for tenant admins', function () {
        // Users table is now part of tenant migrations
        expect(Schema::connection('tenant')->hasTable('users'))->toBeTrue();

        $columns = Schema::connection('tenant')->getColumnListing('users');
        expect($columns)->toContain('id');
        expect($columns)->toContain('name');
        expect($columns)->toContain('email');
        expect($columns)->toContain('password');
    });
});

describe('Foreign Keys and Indexes', function () {
    test('employees table has foreign key to departments', function () {
        // Insert department first
        $departmentId = DB::connection('tenant')->table('departments')->insertGetId([
            'name' => 'IT Department',
            'description' => 'Information Technology',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Insert employee with valid department_id
        $employeeId = DB::connection('tenant')->table('employees')->insertGetId([
            'custom_id' => 'EMP001',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
            'department_id' => $departmentId,
            'is_active' => true,
            'hired_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        expect($employeeId)->toBeGreaterThan(0);

        // Try to insert employee with invalid department_id (should fail)
        try {
            DB::connection('tenant')->table('employees')->insert([
                'custom_id' => 'EMP002',
                'first_name' => 'Jane',
                'last_name' => 'Doe',
                'email' => 'jane@example.com',
                'department_id' => 99999, // Non-existent
                'is_active' => true,
                'hired_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            throw new \Exception('Should have failed with foreign key constraint');
        } catch (\Illuminate\Database\QueryException $e) {
            expect($e->getCode())->toBe('23000'); // Integrity constraint violation
        }
    });

    test('employee_shifts has foreign keys to employees and shifts', function () {
        // Create department
        $departmentId = DB::connection('tenant')->table('departments')->insertGetId([
            'name' => 'HR',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Create employee
        $employeeId = DB::connection('tenant')->table('employees')->insertGetId([
            'custom_id' => 'EMP003',
            'first_name' => 'Alice',
            'last_name' => 'Smith',
            'email' => 'alice@example.com',
            'department_id' => $departmentId,
            'is_active' => true,
            'hired_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Create shift
        $shiftId = DB::connection('tenant')->table('shifts')->insertGetId([
            'name' => 'Day Shift',
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
            'working_days' => json_encode(['monday', 'tuesday', 'wednesday', 'thursday', 'friday']),
            'is_default' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Assign shift to employee
        $assignmentId = DB::connection('tenant')->table('employee_shifts')->insertGetId([
            'employee_id' => $employeeId,
            'shift_id' => $shiftId,
            'effective_from' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        expect($assignmentId)->toBeGreaterThan(0);
    });

    test('attendance_records has foreign keys to employees and devices', function () {
        // Create department
        $departmentId = DB::connection('tenant')->table('departments')->insertGetId([
            'name' => 'Sales',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Create employee
        $employeeId = DB::connection('tenant')->table('employees')->insertGetId([
            'custom_id' => 'EMP004',
            'first_name' => 'Bob',
            'last_name' => 'Johnson',
            'email' => 'bob@example.com',
            'department_id' => $departmentId,
            'is_active' => true,
            'hired_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Create device
        $deviceId = DB::connection('tenant')->table('devices')->insertGetId([
            'device_id' => 'DEV001',
            'name' => 'Main Entrance',
            'location' => 'Building A',
            'ip_address' => '192.168.1.100',
            'capacity' => 3000,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Create attendance record
        $recordId = DB::connection('tenant')->table('attendance_records')->insertGetId([
            'employee_id' => $employeeId,
            'device_id' => $deviceId,
            'recorded_at' => now(),
            'direction' => 'check-in',
            'recognition_score' => 0.95,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        expect($recordId)->toBeGreaterThan(0);
    });

    test('custom_id is unique in employees table', function () {
        $departmentId = DB::connection('tenant')->table('departments')->insertGetId([
            'name' => 'Engineering',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Insert first employee
        DB::connection('tenant')->table('employees')->insert([
            'custom_id' => 'EMP005',
            'first_name' => 'Charlie',
            'last_name' => 'Brown',
            'email' => 'charlie@example.com',
            'department_id' => $departmentId,
            'is_active' => true,
            'hired_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Try to insert second employee with same custom_id (should fail)
        try {
            DB::connection('tenant')->table('employees')->insert([
                'custom_id' => 'EMP005', // Duplicate
                'first_name' => 'David',
                'last_name' => 'Wilson',
                'email' => 'david@example.com',
                'department_id' => $departmentId,
                'is_active' => true,
                'hired_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            throw new \Exception('Should have failed with unique constraint');
        } catch (\Illuminate\Database\QueryException $e) {
            expect($e->getCode())->toBe('23000');
        }
    });

    test('device_id is unique in devices table', function () {
        // Insert first device
        DB::connection('tenant')->table('devices')->insert([
            'device_id' => 'DEV002',
            'name' => 'Back Door',
            'location' => 'Building B',
            'ip_address' => '192.168.1.101',
            'capacity' => 2000,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Try to insert second device with same device_id (should fail)
        try {
            DB::connection('tenant')->table('devices')->insert([
                'device_id' => 'DEV002', // Duplicate
                'name' => 'Side Door',
                'location' => 'Building C',
                'ip_address' => '192.168.1.102',
                'capacity' => 1500,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            throw new \Exception('Should have failed with unique constraint');
        } catch (\Illuminate\Database\QueryException $e) {
            expect($e->getCode())->toBe('23000');
        }
    });
});

describe('Cascade Deletes', function () {
    test('deleting department cascades to employees', function () {
        // Create department
        $departmentId = DB::connection('tenant')->table('departments')->insertGetId([
            'name' => 'Marketing',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Create employee in department
        DB::connection('tenant')->table('employees')->insert([
            'custom_id' => 'EMP006',
            'first_name' => 'Eve',
            'last_name' => 'Davis',
            'email' => 'eve@example.com',
            'department_id' => $departmentId,
            'is_active' => true,
            'hired_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Verify employee exists
        $employeeCount = DB::connection('tenant')->table('employees')
            ->where('department_id', $departmentId)
            ->count();
        expect($employeeCount)->toBe(1);

        // Delete department
        DB::connection('tenant')->table('departments')->where('id', $departmentId)->delete();

        // Verify employee was also deleted (cascade)
        $employeeCount = DB::connection('tenant')->table('employees')
            ->where('department_id', $departmentId)
            ->count();
        expect($employeeCount)->toBe(0);
    });

    test('deleting employee cascades to device_enrollments and attendance_records', function () {
        // Create department
        $departmentId = DB::connection('tenant')->table('departments')->insertGetId([
            'name' => 'Operations',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Create employee
        $employeeId = DB::connection('tenant')->table('employees')->insertGetId([
            'custom_id' => 'EMP007',
            'first_name' => 'Frank',
            'last_name' => 'Miller',
            'email' => 'frank@example.com',
            'department_id' => $departmentId,
            'is_active' => true,
            'hired_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Create device
        $deviceId = DB::connection('tenant')->table('devices')->insertGetId([
            'device_id' => 'DEV003',
            'name' => 'Office Entry',
            'location' => 'Floor 1',
            'ip_address' => '192.168.1.103',
            'capacity' => 5000,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Create device enrollment
        DB::connection('tenant')->table('device_enrollments')->insert([
            'employee_id' => $employeeId,
            'device_id' => $deviceId,
            'enrollment_status' => 'synced',
            'synced_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Create attendance record
        DB::connection('tenant')->table('attendance_records')->insert([
            'employee_id' => $employeeId,
            'device_id' => $deviceId,
            'recorded_at' => now(),
            'direction' => 'check-in',
            'recognition_score' => 0.98,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Verify records exist
        expect(DB::connection('tenant')->table('device_enrollments')->where('employee_id', $employeeId)->count())->toBe(1);
        expect(DB::connection('tenant')->table('attendance_records')->where('employee_id', $employeeId)->count())->toBe(1);

        // Delete employee
        DB::connection('tenant')->table('employees')->where('id', $employeeId)->delete();

        // Verify cascade deletes
        expect(DB::connection('tenant')->table('device_enrollments')->where('employee_id', $employeeId)->count())->toBe(0);
        expect(DB::connection('tenant')->table('attendance_records')->where('employee_id', $employeeId)->count())->toBe(0);
    });
});
