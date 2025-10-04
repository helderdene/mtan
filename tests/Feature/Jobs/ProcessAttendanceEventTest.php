<?php

use App\DTOs\AttendanceEventDTO;
use App\Jobs\ProcessAttendanceEvent;
use App\Models\DeviceRegistry;
use App\Models\Tenant;
use App\Models\Tenant\AttendanceRecord;
use App\Models\Tenant\Department;
use App\Models\Tenant\Device;
use App\Models\Tenant\Employee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Run central database migrations
    Artisan::call('migrate', ['--path' => 'database/migrations/central']);

    // Create test tenant in central database
    $this->tenant = Tenant::create([
        'id' => 'tenant_test_job_001',
        'company_name' => 'Test Company',
        'subdomain' => 'test-job',
        'domain' => null,
        'database_name' => 'tenant_test_job',
        'database_host' => env('DB_HOST', '127.0.0.1'),
        'subscription_plan' => 'professional',
        'max_employees' => 100,
        'max_devices' => 10,
        'is_active' => true,
    ]);

    // Register device in central database
    $this->deviceRegistry = DeviceRegistry::create([
        'device_id' => 'DEVICE_JOB_001',
        'tenant_id' => $this->tenant->id,
        'device_name' => 'Test Device',
        'device_type' => 'biometric',
        'location' => 'Test Location',
        'is_active' => true,
    ]);

    // Provision tenant database
    $tenantDto = new \App\DTOs\Tenant(
        id: $this->tenant->id,
        company_name: $this->tenant->company_name,
        subdomain: $this->tenant->subdomain,
        domain: $this->tenant->domain,
        database_name: $this->tenant->database_name,
        database_host: $this->tenant->database_host,
        subscription_plan: $this->tenant->subscription_plan,
        max_employees: $this->tenant->max_employees,
        max_devices: $this->tenant->max_devices,
        is_active: $this->tenant->is_active,
    );

    $manager = new \App\Services\Tenancy\TenantDatabaseManager;
    $manager->provisionTenant($tenantDto);

    // Set up tenant connection and create test data
    $manager->setupTenantConnection($tenantDto);

    // Create test data in tenant database using explicit connection
    $this->department = Department::on('tenant')->create(['name' => 'Engineering']);

    $this->employee = Employee::on('tenant')->create([
        'custom_id' => 'EMP_JOB_001',
        'first_name' => 'Test',
        'last_name' => 'Employee',
        'email' => 'test@example.com',
        'department_id' => $this->department->id,
        'is_active' => true,
    ]);

    $this->device = Device::on('tenant')->create([
        'device_id' => 'DEVICE_JOB_001',
        'name' => 'Test Device',
        'location' => 'Test Location',
        'ip_address' => '192.168.1.100',
        'capacity' => 3000,
        'is_active' => true,
    ]);
});

afterEach(function () {
    // Drop test database
    $pdo = new \PDO(
        'mysql:host='.env('DB_HOST', '127.0.0.1'),
        env('DB_USERNAME', 'root'),
        env('DB_PASSWORD', '')
    );
    $pdo->exec('DROP DATABASE IF EXISTS tenant_test_job');
});

describe('ProcessAttendanceEvent Job', function () {
    test('creates attendance record for valid recognition event', function () {
        $event = new AttendanceEventDTO(
            device_id: 'DEVICE_JOB_001',
            person_id: 'EMP_JOB_001',
            timestamp: '2025-10-02 10:30:00',
            event_type: 'recognition',
            temperature: 36.5,
            mask: true,
            similarity: 0.9850,
        );

        $job = new ProcessAttendanceEvent($event);
        app()->call([$job, 'handle']);

        $record = AttendanceRecord::on('tenant')->first();

        expect($record)->not->toBeNull();
        expect($record->employee_id)->toBe($this->employee->id);
        expect($record->device_id)->toBe($this->device->id);
        expect($record->direction)->toBe('check-in');
        expect($record->recognition_score)->toBe('0.9850');
    });

    test('resolves tenant from device_id', function () {
        $event = new AttendanceEventDTO(
            device_id: 'DEVICE_JOB_001',
            person_id: 'EMP_JOB_001',
            timestamp: '2025-10-02 10:30:00',
            event_type: 'recognition',
        );

        $job = new ProcessAttendanceEvent($event);
        app()->call([$job, 'handle']);

        expect(AttendanceRecord::on('tenant')->count())->toBe(1);
    });

    test('looks up employee by custom_id', function () {
        $event = new AttendanceEventDTO(
            device_id: 'DEVICE_JOB_001',
            person_id: 'EMP_JOB_001',
            timestamp: '2025-10-02 10:30:00',
            event_type: 'recognition',
        );

        $job = new ProcessAttendanceEvent($event);
        app()->call([$job, 'handle']);

        $record = AttendanceRecord::on('tenant')->first();

        expect($record->employee->custom_id)->toBe('EMP_JOB_001');
    });

    test('sets direction to check-in for Phase 1', function () {
        $event = new AttendanceEventDTO(
            device_id: 'DEVICE_JOB_001',
            person_id: 'EMP_JOB_001',
            timestamp: '2025-10-02 10:30:00',
            event_type: 'recognition',
        );

        $job = new ProcessAttendanceEvent($event);
        app()->call([$job, 'handle']);

        $record = AttendanceRecord::on('tenant')->first();

        expect($record->direction)->toBe('check-in');
    });

    test('prevents duplicate records within 1 minute', function () {
        $timestamp = '2025-10-02 10:30:00';

        // Create first event
        $event1 = new AttendanceEventDTO(
            device_id: 'DEVICE_JOB_001',
            person_id: 'EMP_JOB_001',
            timestamp: $timestamp,
            event_type: 'recognition',
        );

        $job1 = new ProcessAttendanceEvent($event1);
        app()->call([$job1, 'handle']);

        // Try to create duplicate event 30 seconds later
        $event2 = new AttendanceEventDTO(
            device_id: 'DEVICE_JOB_001',
            person_id: 'EMP_JOB_001',
            timestamp: '2025-10-02 10:30:30',
            event_type: 'recognition',
        );

        $job2 = new ProcessAttendanceEvent($event2);
        app()->call([$job2, 'handle']);

        // Should only have one record
        expect(AttendanceRecord::on('tenant')->count())->toBe(1);
    });

    test('allows records after 1 minute window', function () {
        $timestamp1 = '2025-10-02 10:30:00';
        $timestamp2 = '2025-10-02 10:31:30'; // 1.5 minutes later

        // Create first event
        $event1 = new AttendanceEventDTO(
            device_id: 'DEVICE_JOB_001',
            person_id: 'EMP_JOB_001',
            timestamp: $timestamp1,
            event_type: 'recognition',
        );

        $job1 = new ProcessAttendanceEvent($event1);
        app()->call([$job1, 'handle']);

        // Create second event after 1 minute window
        $event2 = new AttendanceEventDTO(
            device_id: 'DEVICE_JOB_001',
            person_id: 'EMP_JOB_001',
            timestamp: $timestamp2,
            event_type: 'recognition',
        );

        $job2 = new ProcessAttendanceEvent($event2);
        app()->call([$job2, 'handle']);

        // Should have two records
        expect(AttendanceRecord::on('tenant')->count())->toBe(2);
    });

    test('handles unregistered device gracefully', function () {
        $event = new AttendanceEventDTO(
            device_id: 'UNKNOWN_DEVICE',
            person_id: 'EMP_JOB_001',
            timestamp: '2025-10-02 10:30:00',
            event_type: 'recognition',
        );

        $job = new ProcessAttendanceEvent($event);
        app()->call([$job, 'handle']);

        // Should not create any records
        expect(AttendanceRecord::on('tenant')->count())->toBe(0);
    });

    test('handles unknown employee gracefully', function () {
        $event = new AttendanceEventDTO(
            device_id: 'DEVICE_JOB_001',
            person_id: 'UNKNOWN_EMP',
            timestamp: '2025-10-02 10:30:00',
            event_type: 'recognition',
        );

        $job = new ProcessAttendanceEvent($event);
        app()->call([$job, 'handle']);

        // Should not create any records
        expect(AttendanceRecord::on('tenant')->count())->toBe(0);
    });

    test('handles inactive employee', function () {
        // Deactivate employee
        $this->employee->update(['is_active' => false]);

        $event = new AttendanceEventDTO(
            device_id: 'DEVICE_JOB_001',
            person_id: 'EMP_JOB_001',
            timestamp: '2025-10-02 10:30:00',
            event_type: 'recognition',
        );

        $job = new ProcessAttendanceEvent($event);
        app()->call([$job, 'handle']);

        // Should not create records for inactive employees
        expect(AttendanceRecord::on('tenant')->count())->toBe(0);
    });

    test('logs stranger events but does not create attendance records', function () {
        $event = new AttendanceEventDTO(
            device_id: 'DEVICE_JOB_001',
            person_id: null,
            timestamp: '2025-10-02 10:30:00',
            event_type: 'stranger',
            image_url: '/storage/strangers/20251002_103000.jpg',
        );

        $job = new ProcessAttendanceEvent($event);
        app()->call([$job, 'handle']);

        // Should not create attendance record for stranger
        expect(AttendanceRecord::on('tenant')->count())->toBe(0);
    });

    test('stores recognition score when provided', function () {
        $event = new AttendanceEventDTO(
            device_id: 'DEVICE_JOB_001',
            person_id: 'EMP_JOB_001',
            timestamp: '2025-10-02 10:30:00',
            event_type: 'recognition',
            similarity: 0.9512,
        );

        $job = new ProcessAttendanceEvent($event);
        app()->call([$job, 'handle']);

        $record = AttendanceRecord::on('tenant')->first();

        expect($record->recognition_score)->toBe('0.9512');
    });
});
