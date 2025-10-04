<?php

use App\Models\Tenant\AttendanceRecord;
use App\Models\Tenant\Department;
use App\Models\Tenant\Device;
use App\Models\Tenant\Employee;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    // Create test tenant using TenantDatabaseManager
    $tenant = new \App\DTOs\Tenant(
        id: 'attendance-record-test',
        company_name: 'Test Company',
        subdomain: 'test',
        domain: null,
        database_name: 'tenant_test_attendance',
        database_host: env('DB_HOST', '127.0.0.1'),
        subscription_plan: 'professional',
        max_employees: 100,
        max_devices: 10,
        is_active: true,
    );

    $manager = new \App\Services\Tenancy\TenantDatabaseManager();
    $manager->provisionTenant($tenant);

    // Set default connection to tenant for models
    Config::set('database.default', 'tenant');
});

afterEach(function () {
    // Restore default connection
    Config::set('database.default', 'sqlite');

    // Drop test database
    $pdo = new \PDO(
        'mysql:host=' . env('DB_HOST', '127.0.0.1'),
        env('DB_USERNAME', 'root'),
        env('DB_PASSWORD', '')
    );
    $pdo->exec('DROP DATABASE IF EXISTS tenant_test_attendance');
});

describe('AttendanceRecord Model', function () {
    test('can create attendance record', function () {
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

        $record = AttendanceRecord::create([
            'employee_id' => $employee->id,
            'device_id' => $device->id,
            'recorded_at' => now(),
            'direction' => 'check-in',
            'recognition_score' => 0.9850,
        ]);

        expect($record)->toBeInstanceOf(AttendanceRecord::class);
        expect($record->id)->not->toBeNull();
        expect($record->direction)->toBe('check-in');
        expect($record->recognition_score)->toBe('0.9850');
    });

    test('has employee relationship', function () {
        $department = Department::create(['name' => 'Sales']);
        $employee = Employee::create([
            'custom_id' => 'EMP002',
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'email' => 'jane@example.com',
            'department_id' => $department->id,
        ]);

        $device = Device::create([
            'device_id' => 'DEVICE002',
            'name' => 'Side Door',
            'location' => 'Building B',
            'ip_address' => '192.168.1.101',
            'capacity' => 3000,
            'is_active' => true,
        ]);

        $record = AttendanceRecord::create([
            'employee_id' => $employee->id,
            'device_id' => $device->id,
            'recorded_at' => now(),
            'direction' => 'check-in',
        ]);

        expect($record->employee)->toBeInstanceOf(Employee::class);
        expect($record->employee->custom_id)->toBe('EMP002');
    });

    test('has device relationship', function () {
        $department = Department::create(['name' => 'IT']);
        $employee = Employee::create([
            'custom_id' => 'EMP003',
            'first_name' => 'Bob',
            'last_name' => 'Johnson',
            'email' => 'bob@example.com',
            'department_id' => $department->id,
        ]);

        $device = Device::create([
            'device_id' => 'DEVICE003',
            'name' => 'Back Door',
            'location' => 'Building C',
            'ip_address' => '192.168.1.102',
            'capacity' => 3000,
            'is_active' => true,
        ]);

        $record = AttendanceRecord::create([
            'employee_id' => $employee->id,
            'device_id' => $device->id,
            'recorded_at' => now(),
            'direction' => 'check-out',
        ]);

        expect($record->device)->toBeInstanceOf(Device::class);
        expect($record->device->device_id)->toBe('DEVICE003');
    });

    test('by direction scope filters records', function () {
        $department = Department::create(['name' => 'HR']);
        $employee = Employee::create([
            'custom_id' => 'EMP004',
            'first_name' => 'Alice',
            'last_name' => 'Williams',
            'email' => 'alice@example.com',
            'department_id' => $department->id,
        ]);

        $device = Device::create([
            'device_id' => 'DEVICE004',
            'name' => 'Front Gate',
            'location' => 'Entrance',
            'ip_address' => '192.168.1.103',
            'capacity' => 3000,
            'is_active' => true,
        ]);

        AttendanceRecord::create([
            'employee_id' => $employee->id,
            'device_id' => $device->id,
            'recorded_at' => now()->subHours(8),
            'direction' => 'check-in',
        ]);

        AttendanceRecord::create([
            'employee_id' => $employee->id,
            'device_id' => $device->id,
            'recorded_at' => now(),
            'direction' => 'check-out',
        ]);

        $checkInRecords = AttendanceRecord::byDirection('check-in')->get();
        $checkOutRecords = AttendanceRecord::byDirection('check-out')->get();

        expect($checkInRecords)->toHaveCount(1);
        expect($checkOutRecords)->toHaveCount(1);
    });

    test('date range scope filters records', function () {
        $department = Department::create(['name' => 'Operations']);
        $employee = Employee::create([
            'custom_id' => 'EMP005',
            'first_name' => 'Charlie',
            'last_name' => 'Brown',
            'email' => 'charlie@example.com',
            'department_id' => $department->id,
        ]);

        $device = Device::create([
            'device_id' => 'DEVICE005',
            'name' => 'Office Door',
            'location' => 'Floor 1',
            'ip_address' => '192.168.1.104',
            'capacity' => 3000,
            'is_active' => true,
        ]);

        // Create records for different dates
        AttendanceRecord::create([
            'employee_id' => $employee->id,
            'device_id' => $device->id,
            'recorded_at' => now()->subDays(5),
            'direction' => 'check-in',
        ]);

        AttendanceRecord::create([
            'employee_id' => $employee->id,
            'device_id' => $device->id,
            'recorded_at' => now()->subDays(2),
            'direction' => 'check-in',
        ]);

        AttendanceRecord::create([
            'employee_id' => $employee->id,
            'device_id' => $device->id,
            'recorded_at' => now(),
            'direction' => 'check-in',
        ]);

        $startDate = now()->subDays(3)->startOfDay();
        $endDate = now()->endOfDay();

        $recentRecords = AttendanceRecord::dateRange($startDate, $endDate)->get();

        expect($recentRecords)->toHaveCount(2);
    });

    test('recorded_at is cast to datetime', function () {
        $department = Department::create(['name' => 'Finance']);
        $employee = Employee::create([
            'custom_id' => 'EMP006',
            'first_name' => 'David',
            'last_name' => 'Miller',
            'email' => 'david@example.com',
            'department_id' => $department->id,
        ]);

        $device = Device::create([
            'device_id' => 'DEVICE006',
            'name' => 'Parking Gate',
            'location' => 'Parking Lot',
            'ip_address' => '192.168.1.105',
            'capacity' => 3000,
            'is_active' => true,
        ]);

        $now = now();
        $record = AttendanceRecord::create([
            'employee_id' => $employee->id,
            'device_id' => $device->id,
            'recorded_at' => $now,
            'direction' => 'check-in',
        ]);

        expect($record->recorded_at)->toBeInstanceOf(\Illuminate\Support\Carbon::class);
        expect($record->recorded_at->format('Y-m-d H:i:s'))->toBe($now->format('Y-m-d H:i:s'));
    });

    test('recognition_score is cast to decimal', function () {
        $department = Department::create(['name' => 'Security']);
        $employee = Employee::create([
            'custom_id' => 'EMP007',
            'first_name' => 'Eve',
            'last_name' => 'Davis',
            'email' => 'eve@example.com',
            'department_id' => $department->id,
        ]);

        $device = Device::create([
            'device_id' => 'DEVICE007',
            'name' => 'Security Gate',
            'location' => 'Perimeter',
            'ip_address' => '192.168.1.106',
            'capacity' => 3000,
            'is_active' => true,
        ]);

        $record = AttendanceRecord::create([
            'employee_id' => $employee->id,
            'device_id' => $device->id,
            'recorded_at' => now(),
            'direction' => 'check-in',
            'recognition_score' => 0.9876,
        ]);

        expect($record->recognition_score)->toBe('0.9876');
    });

    test('supports all direction types', function () {
        $department = Department::create(['name' => 'Marketing']);
        $employee = Employee::create([
            'custom_id' => 'EMP008',
            'first_name' => 'Frank',
            'last_name' => 'Wilson',
            'email' => 'frank@example.com',
            'department_id' => $department->id,
        ]);

        $device = Device::create([
            'device_id' => 'DEVICE008',
            'name' => 'Cafeteria',
            'location' => 'Ground Floor',
            'ip_address' => '192.168.1.107',
            'capacity' => 3000,
            'is_active' => true,
        ]);

        $directions = ['check-in', 'check-out', 'break-start', 'break-end'];

        foreach ($directions as $direction) {
            $record = AttendanceRecord::create([
                'employee_id' => $employee->id,
                'device_id' => $device->id,
                'recorded_at' => now(),
                'direction' => $direction,
            ]);

            expect($record->direction)->toBe($direction);
        }
    });

    test('can query records with employee and device relationships', function () {
        $department = Department::create(['name' => 'R&D']);
        $employee = Employee::create([
            'custom_id' => 'EMP009',
            'first_name' => 'Grace',
            'last_name' => 'Taylor',
            'email' => 'grace@example.com',
            'department_id' => $department->id,
        ]);

        $device = Device::create([
            'device_id' => 'DEVICE009',
            'name' => 'Lab Door',
            'location' => 'Research Wing',
            'ip_address' => '192.168.1.108',
            'capacity' => 3000,
            'is_active' => true,
        ]);

        AttendanceRecord::create([
            'employee_id' => $employee->id,
            'device_id' => $device->id,
            'recorded_at' => now(),
            'direction' => 'check-in',
            'recognition_score' => 0.95,
        ]);

        $record = AttendanceRecord::with(['employee', 'device'])->first();

        expect($record->employee->full_name)->toBe('Grace Taylor');
        expect($record->device->name)->toBe('Lab Door');
    });

    test('orders records by recorded_at descending by default', function () {
        $department = Department::create(['name' => 'Support']);
        $employee = Employee::create([
            'custom_id' => 'EMP010',
            'first_name' => 'Henry',
            'last_name' => 'Anderson',
            'email' => 'henry@example.com',
            'department_id' => $department->id,
        ]);

        $device = Device::create([
            'device_id' => 'DEVICE010',
            'name' => 'Help Desk',
            'location' => 'Floor 2',
            'ip_address' => '192.168.1.109',
            'capacity' => 3000,
            'is_active' => true,
        ]);

        $oldRecord = AttendanceRecord::create([
            'employee_id' => $employee->id,
            'device_id' => $device->id,
            'recorded_at' => now()->subHours(2),
            'direction' => 'check-in',
        ]);

        $newRecord = AttendanceRecord::create([
            'employee_id' => $employee->id,
            'device_id' => $device->id,
            'recorded_at' => now(),
            'direction' => 'check-out',
        ]);

        $records = AttendanceRecord::latest('recorded_at')->get();

        expect($records->first()->id)->toBe($newRecord->id);
        expect($records->last()->id)->toBe($oldRecord->id);
    });
});
