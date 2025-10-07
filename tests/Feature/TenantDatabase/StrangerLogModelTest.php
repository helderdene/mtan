<?php

use App\Models\Tenant\Department;
use App\Models\Tenant\Device;
use App\Models\Tenant\Employee;
use App\Models\Tenant\StrangerLog;
use App\Models\User;
use Illuminate\Support\Facades\Config;

beforeEach(function () {
    // Create test tenant using TenantDatabaseManager
    $tenant = new \App\DTOs\Tenant(
        id: 'stranger-log-test',
        company_name: 'Test Company',
        subdomain: 'test',
        domain: null,
        database_name: 'tenant_test_stranger',
        database_host: env('DB_HOST', '127.0.0.1'),
        subscription_plan: 'professional',
        max_employees: 100,
        max_devices: 10,
        is_active: true,
    );

    $manager = new \App\Services\Tenancy\TenantDatabaseManager;
    $manager->provisionTenant($tenant);

    // Set default connection to tenant for models
    Config::set('database.default', 'tenant');
});

afterEach(function () {
    // Restore default connection
    Config::set('database.default', 'sqlite');

    // Drop test database
    $pdo = new \PDO(
        'mysql:host='.env('DB_HOST', '127.0.0.1'),
        env('DB_USERNAME', 'root'),
        env('DB_PASSWORD', '')
    );
    $pdo->exec('DROP DATABASE IF EXISTS tenant_test_stranger');
});

describe('StrangerLog Model', function () {
    test('can create stranger log', function () {
        $device = Device::create([
            'device_id' => 'DEVICE001',
            'name' => 'Main Entrance',
            'location' => 'Building A',
            'ip_address' => '192.168.1.100',
            'capacity' => 3000,
            'is_active' => true,
        ]);

        $log = StrangerLog::create([
            'device_id' => $device->id,
            'detected_at' => now(),
            'photo_path' => 'strangers/photos/test.jpg',
            'match_status' => 'unreviewed',
        ]);

        expect($log)->toBeInstanceOf(StrangerLog::class);
        expect($log->id)->not->toBeNull();
        expect($log->match_status)->toBe('unreviewed');
        expect($log->photo_path)->toBe('strangers/photos/test.jpg');
    });

    test('has device relationship', function () {
        $device = Device::create([
            'device_id' => 'DEVICE001',
            'name' => 'Main Entrance',
            'location' => 'Building A',
            'ip_address' => '192.168.1.100',
            'capacity' => 3000,
            'is_active' => true,
        ]);

        $log = StrangerLog::create([
            'device_id' => $device->id,
            'detected_at' => now(),
            'photo_path' => 'strangers/photos/test.jpg',
        ]);

        expect($log->device)->toBeInstanceOf(Device::class);
        expect($log->device->device_id)->toBe('DEVICE001');
    });

    test('has employee relationship when matched', function () {
        $department = Department::create(['name' => 'Engineering']);
        $employee = Employee::create([
            'custom_id' => 'EMP001',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
            'department_id' => $department->id,
        ]);

        $device = Device::create([
            'device_id' => 'DEVICE001',
            'name' => 'Main Entrance',
            'location' => 'Building A',
            'ip_address' => '192.168.1.100',
            'capacity' => 3000,
            'is_active' => true,
        ]);

        $log = StrangerLog::create([
            'device_id' => $device->id,
            'employee_id' => $employee->id,
            'detected_at' => now(),
            'photo_path' => 'strangers/photos/test.jpg',
            'match_status' => 'matched',
        ]);

        expect($log->employee)->toBeInstanceOf(Employee::class);
        expect($log->employee->custom_id)->toBe('EMP001');
    });

    test('has matchedBy relationship', function () {
        // Create user in default database (not tenant)
        Config::set('database.default', 'sqlite');
        $user = User::factory()->create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
        ]);
        Config::set('database.default', 'tenant');

        $device = Device::create([
            'device_id' => 'DEVICE001',
            'name' => 'Main Entrance',
            'location' => 'Building A',
            'ip_address' => '192.168.1.100',
            'capacity' => 3000,
            'is_active' => true,
        ]);

        $log = StrangerLog::create([
            'device_id' => $device->id,
            'matched_by' => $user->id,
            'detected_at' => now(),
            'photo_path' => 'strangers/photos/test.jpg',
            'match_status' => 'matched',
        ]);

        expect($log->matchedBy)->toBeInstanceOf(User::class);
        expect($log->matchedBy->email)->toBe('admin@example.com');
    });

    test('can query unreviewed logs', function () {
        $device = Device::create([
            'device_id' => 'DEVICE001',
            'name' => 'Main Entrance',
            'location' => 'Building A',
            'ip_address' => '192.168.1.100',
            'capacity' => 3000,
            'is_active' => true,
        ]);

        StrangerLog::create([
            'device_id' => $device->id,
            'detected_at' => now(),
            'photo_path' => 'strangers/photos/test1.jpg',
            'match_status' => 'unreviewed',
        ]);

        StrangerLog::create([
            'device_id' => $device->id,
            'detected_at' => now(),
            'photo_path' => 'strangers/photos/test2.jpg',
            'match_status' => 'matched',
        ]);

        $unreviewedCount = StrangerLog::where('match_status', 'unreviewed')->count();
        expect($unreviewedCount)->toBe(1);
    });

    test('can add notes to stranger log', function () {
        $device = Device::create([
            'device_id' => 'DEVICE001',
            'name' => 'Main Entrance',
            'location' => 'Building A',
            'ip_address' => '192.168.1.100',
            'capacity' => 3000,
            'is_active' => true,
        ]);

        $log = StrangerLog::create([
            'device_id' => $device->id,
            'detected_at' => now(),
            'photo_path' => 'strangers/photos/test.jpg',
            'match_status' => 'security_issue',
            'notes' => 'Suspicious behavior detected',
        ]);

        expect($log->notes)->toBe('Suspicious behavior detected');
    });
});
