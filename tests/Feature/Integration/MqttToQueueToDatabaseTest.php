<?php

use App\Models\DeviceRegistry;
use App\Models\Tenant;
use App\Models\Tenant\AttendanceRecord;
use App\Models\Tenant\Department;
use App\Models\Tenant\Device;
use App\Models\Tenant\Employee;
use App\Services\MQTT\MessageHandler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Drop test database if it exists from previous run
    try {
        $pdo = new \PDO(
            'mysql:host='.env('DB_HOST', '127.0.0.1'),
            env('DB_USERNAME', 'root'),
            env('DB_PASSWORD', '')
        );
        $pdo->exec('DROP DATABASE IF EXISTS tenant_test_integration');
    } catch (\Exception $e) {
        // Ignore errors if database doesn't exist
    }

    // Run central database migrations
    Artisan::call('migrate', ['--path' => 'database/migrations/central']);

    // Create test tenant in central database
    $this->tenant = Tenant::create([
        'id' => 'tenant_integration_001',
        'company_name' => 'Integration Test Company',
        'subdomain' => 'integration-test',
        'domain' => null,
        'database_name' => 'tenant_test_integration',
        'database_host' => env('DB_HOST', '127.0.0.1'),
        'subscription_plan' => 'professional',
        'max_employees' => 100,
        'max_devices' => 10,
        'is_active' => true,
    ]);

    // Register device in central database
    $this->deviceRegistry = DeviceRegistry::create([
        'device_id' => 'DEVICE_INT_001',
        'tenant_id' => $this->tenant->id,
        'device_name' => 'Integration Test Device',
        'device_type' => 'biometric',
        'location' => 'Main Entrance',
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
    $manager->setupTenantConnection($tenantDto);

    // Create test data in tenant database
    $this->department = Department::on('tenant')->create(['name' => 'Engineering']);

    $this->employee = Employee::on('tenant')->create([
        'custom_id' => 'EMP_INT_001',
        'first_name' => 'Integration',
        'last_name' => 'Test',
        'email' => 'integration@example.com',
        'department_id' => $this->department->id,
        'is_active' => true,
    ]);

    $this->device = Device::on('tenant')->create([
        'device_id' => 'DEVICE_INT_001',
        'name' => 'Main Entrance',
        'location' => 'Building A',
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
    $pdo->exec('DROP DATABASE IF EXISTS tenant_test_integration');
});

describe('MQTT to Queue to Database Integration', function () {
    test('processes recognition event from MQTT message through queue to database', function () {
        // Arrange: Create MQTT payload
        $topic = 'device/DEVICE_INT_001/recognition';
        $payload = json_encode([
            'device_id' => 'DEVICE_INT_001',
            'person_id' => 'EMP_INT_001',
            'timestamp' => '2025-10-02 14:30:00',
            'temperature' => 36.5,
            'mask' => true,
            'similarity' => 0.9750,
        ]);

        // Act: Handle MQTT message (which dispatches job to queue)
        $handler = new MessageHandler;
        $handler->handle($topic, $payload);

        // Process queued jobs
        Artisan::call('queue:work', [
            '--once' => true,
            '--queue' => 'attendance',
        ]);

        // Assert: Verify attendance record was created in tenant database
        $record = AttendanceRecord::on('tenant')->first();

        expect($record)->not->toBeNull();
        expect($record->employee_id)->toBe($this->employee->id);
        expect($record->device_id)->toBe($this->device->id);
        expect($record->direction)->toBe('check-in');
        expect($record->recognition_score)->toBe('0.9750');
        expect($record->recorded_at->format('Y-m-d H:i:s'))->toBe('2025-10-02 14:30:00');
    });

    test('handles stranger event correctly through full flow', function () {
        // Arrange: Create stranger MQTT payload
        $topic = 'device/DEVICE_INT_001/stranger';
        $payload = json_encode([
            'device_id' => 'DEVICE_INT_001',
            'timestamp' => '2025-10-02 14:35:00',
            'image_url' => '/storage/strangers/20251002_143500.jpg',
        ]);

        // Act: Handle MQTT message and process queue
        $handler = new MessageHandler;
        $handler->handle($topic, $payload);

        Artisan::call('queue:work', [
            '--once' => true,
            '--queue' => 'attendance',
        ]);

        // Assert: No attendance record should be created for strangers
        expect(AttendanceRecord::on('tenant')->count())->toBe(0);
    });

    test('handles duplicate detection through full flow', function () {
        // Arrange: Create first recognition payload
        $topic = 'device/DEVICE_INT_001/recognition';
        $payload1 = json_encode([
            'device_id' => 'DEVICE_INT_001',
            'person_id' => 'EMP_INT_001',
            'timestamp' => '2025-10-02 15:00:00',
            'similarity' => 0.9650,
        ]);

        $payload2 = json_encode([
            'device_id' => 'DEVICE_INT_001',
            'person_id' => 'EMP_INT_001',
            'timestamp' => '2025-10-02 15:00:30', // 30 seconds later
            'similarity' => 0.9700,
        ]);

        // Act: Handle both messages
        $handler = new MessageHandler;
        $handler->handle($topic, $payload1);
        $handler->handle($topic, $payload2);

        // Process both jobs
        Artisan::call('queue:work', [
            '--stop-when-empty' => true,
            '--queue' => 'attendance',
        ]);

        // Assert: Only one record should be created (duplicate prevented)
        expect(AttendanceRecord::on('tenant')->count())->toBe(1);
    });

    test('allows multiple check-ins after 1 minute window', function () {
        // Arrange: Create two recognition payloads more than 1 minute apart
        $topic = 'device/DEVICE_INT_001/recognition';
        $payload1 = json_encode([
            'device_id' => 'DEVICE_INT_001',
            'person_id' => 'EMP_INT_001',
            'timestamp' => '2025-10-02 15:00:00',
            'similarity' => 0.9650,
        ]);

        $payload2 = json_encode([
            'device_id' => 'DEVICE_INT_001',
            'person_id' => 'EMP_INT_001',
            'timestamp' => '2025-10-02 15:02:00', // 2 minutes later
            'similarity' => 0.9700,
        ]);

        // Act: Handle both messages
        $handler = new MessageHandler;
        $handler->handle($topic, $payload1);
        $handler->handle($topic, $payload2);

        // Process both jobs
        Artisan::call('queue:work', [
            '--stop-when-empty' => true,
            '--queue' => 'attendance',
        ]);

        // Assert: Two records should be created
        expect(AttendanceRecord::on('tenant')->count())->toBe(2);
    });

    test('job is dispatched to attendance queue', function () {
        Queue::fake();

        // Arrange
        $topic = 'device/DEVICE_INT_001/recognition';
        $payload = json_encode([
            'device_id' => 'DEVICE_INT_001',
            'person_id' => 'EMP_INT_001',
            'timestamp' => '2025-10-02 16:00:00',
            'similarity' => 0.9800,
        ]);

        // Act: Handle MQTT message
        $handler = new MessageHandler;
        $handler->handle($topic, $payload);

        // Assert: Job was dispatched to attendance queue
        Queue::assertPushed(\App\Jobs\ProcessAttendanceEvent::class, function ($job) {
            return $job->queue === 'attendance' &&
                   $job->event->device_id === 'DEVICE_INT_001' &&
                   $job->event->person_id === 'EMP_INT_001';
        });
    });

    test('handles unregistered device gracefully through full flow', function () {
        // Arrange: Create payload for unregistered device
        $topic = 'device/UNKNOWN_DEVICE/recognition';
        $payload = json_encode([
            'device_id' => 'UNKNOWN_DEVICE',
            'person_id' => 'EMP_INT_001',
            'timestamp' => '2025-10-02 16:30:00',
            'similarity' => 0.9800,
        ]);

        // Act: Handle MQTT message and process queue
        $handler = new MessageHandler;
        $handler->handle($topic, $payload);

        Artisan::call('queue:work', [
            '--once' => true,
            '--queue' => 'attendance',
        ]);

        // Assert: No attendance record should be created
        expect(AttendanceRecord::on('tenant')->count())->toBe(0);
    });

    test('handles unknown employee gracefully through full flow', function () {
        // Arrange: Create payload for unknown employee
        $topic = 'device/DEVICE_INT_001/recognition';
        $payload = json_encode([
            'device_id' => 'DEVICE_INT_001',
            'person_id' => 'UNKNOWN_EMP',
            'timestamp' => '2025-10-02 17:00:00',
            'similarity' => 0.9800,
        ]);

        // Act: Handle MQTT message and process queue
        $handler = new MessageHandler;
        $handler->handle($topic, $payload);

        Artisan::call('queue:work', [
            '--once' => true,
            '--queue' => 'attendance',
        ]);

        // Assert: No attendance record should be created
        expect(AttendanceRecord::on('tenant')->count())->toBe(0);
    });

    test('message handler tracks statistics correctly', function () {
        // Arrange
        $topic = 'device/DEVICE_INT_001/recognition';
        $payload = json_encode([
            'device_id' => 'DEVICE_INT_001',
            'person_id' => 'EMP_INT_001',
            'timestamp' => '2025-10-02 18:00:00',
            'similarity' => 0.9800,
        ]);

        $handler = new MessageHandler;

        // Act: Process multiple messages
        $handler->handle($topic, $payload);
        $handler->handle($topic, $payload);
        $handler->handle($topic, $payload);

        // Assert: Statistics are tracked
        $stats = $handler->getStatistics();
        expect($stats['total_processed'])->toBe(3);
        expect($stats['total_errors'])->toBe(0);
        expect($stats['last_processed_at'])->not->toBeNull();
    });
});
