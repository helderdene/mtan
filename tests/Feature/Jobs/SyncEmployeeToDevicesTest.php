<?php

use App\DTOs\Tenant as TenantDTO;
use App\Jobs\SyncEmployeeToDevices;
use App\Models\DeviceRegistry;
use App\Models\Tenant;
use App\Models\Tenant\Department;
use App\Models\Tenant\Device;
use App\Models\Tenant\DeviceEnrollment;
use App\Models\Tenant\Employee;
use App\Services\MQTT\MQTTClient;
use App\Services\Tenancy\TenantDatabaseManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Drop test database if it exists from previous run
    try {
        $pdo = new \PDO(
            'mysql:host=' . env('DB_HOST', '127.0.0.1'),
            env('DB_USERNAME', 'root'),
            env('DB_PASSWORD', '')
        );
        $pdo->exec('DROP DATABASE IF EXISTS tenant_sync_test');
    } catch (\Exception $e) {
        // Ignore errors if database doesn't exist
    }

    // Run central database migrations
    Artisan::call('migrate', ['--path' => 'database/migrations/central']);

    // Create tenant in central database
    $this->tenant = Tenant::create([
        'id' => 'tenant_sync_test_001',
        'company_name' => 'Test Company',
        'subdomain' => 'test-sync',
        'domain' => null,
        'database_name' => 'tenant_sync_test',
        'database_host' => env('DB_HOST', '127.0.0.1'),
        'subscription_plan' => 'enterprise',
        'max_employees' => 1000,
        'max_devices' => 50,
        'is_active' => true,
    ]);

    // Setup tenant database connection
    $manager = app(TenantDatabaseManager::class);
    $tenantDto = new TenantDTO(
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
    $manager->setupTenantConnection($tenantDto);

    // Run tenant migrations
    Artisan::call('migrate', ['--database' => 'tenant', '--path' => 'database/migrations/tenant', '--force' => true]);

    // Create default department for tests
    $this->department = Department::on('tenant')->create([
        'name' => 'IT Department',
        'description' => 'Information Technology',
    ]);
});

afterEach(function () {
    // Drop test database
    try {
        $pdo = new \PDO(
            'mysql:host=' . env('DB_HOST', '127.0.0.1'),
            env('DB_USERNAME', 'root'),
            env('DB_PASSWORD', '')
        );
        $pdo->exec('DROP DATABASE IF EXISTS tenant_sync_test');
    } catch (\Exception $e) {
        // Ignore errors
    }
});

test('SyncEmployeeToDevices Job → publishes AddPerson command to active devices', function () {
    // Create devices in central database (device registry)
    $deviceRegistry1 = DeviceRegistry::create([
        'tenant_id' => $this->tenant->id,
        'device_id' => 'DEV001',
        'device_name' => 'Main Entrance Device',
        'device_type' => 'biometric',
        'location' => 'Building A',
        'is_active' => true,
    ]);

    $deviceRegistry2 = DeviceRegistry::create([
        'tenant_id' => $this->tenant->id,
        'device_id' => 'DEV002',
        'device_name' => 'Back Door Device',
        'device_type' => 'biometric',
        'location' => 'Building B',
        'is_active' => true,
    ]);

    // Create devices in tenant database
    $device1 = Device::on('tenant')->create([
        'device_id' => 'DEV001',
        'name' => 'Main Entrance',
        'location' => 'Building A',
        'ip_address' => '192.168.1.100',
        'capacity' => 5000,
        'is_active' => true,
    ]);

    $device2 = Device::on('tenant')->create([
        'device_id' => 'DEV002',
        'name' => 'Back Door',
        'location' => 'Building B',
        'ip_address' => '192.168.1.101',
        'capacity' => 3000,
        'is_active' => true,
    ]);

    // Create employee
    $employee = Employee::on('tenant')->create([
        'custom_id' => 'EMP123456',
        'first_name' => 'John',
        'last_name' => 'Doe',
        'email' => 'john.doe@example.com',
        'department_id' => $this->department->id,
        'is_active' => true,
    ]);

    // Mock MQTT Client
    $mqttClient = Mockery::mock(MQTTClient::class);
    $this->app->instance(MQTTClient::class, $mqttClient);

    // Expect publish calls for each device with correct payload
    $mqttClient->shouldReceive('publish')
        ->once()
        ->withArgs(function ($topic, $payload) use ($device1, $employee) {
            $data = json_decode($payload, true);

            return $topic === "mqtt/face/{$device1->device_id}/AddPerson"
                && $data['custom_id'] === $employee->custom_id
                && $data['name'] === $employee->full_name
                && isset($data['timestamp']);
        });

    $mqttClient->shouldReceive('publish')
        ->once()
        ->withArgs(function ($topic, $payload) use ($device2, $employee) {
            $data = json_decode($payload, true);

            return $topic === "mqtt/face/{$device2->device_id}/AddPerson"
                && $data['custom_id'] === $employee->custom_id
                && $data['name'] === $employee->full_name
                && isset($data['timestamp']);
        });

    // Dispatch job
    $job = new SyncEmployeeToDevices($employee->id, $this->tenant->id);
    $job->handle($mqttClient, app(TenantDatabaseManager::class));

    // Verify device enrollments were created
    expect(DeviceEnrollment::on('tenant')->count())->toBe(2);

    $enrollment1 = DeviceEnrollment::on('tenant')
        ->where('employee_id', $employee->id)
        ->where('device_id', $device1->id)
        ->first();

    expect($enrollment1)->not->toBeNull()
        ->and($enrollment1->enrollment_status)->toBe('pending');

    $enrollment2 = DeviceEnrollment::on('tenant')
        ->where('employee_id', $employee->id)
        ->where('device_id', $device2->id)
        ->first();

    expect($enrollment2)->not->toBeNull()
        ->and($enrollment2->enrollment_status)->toBe('pending');
});

test('SyncEmployeeToDevices Job → only syncs to active devices', function () {
    // Create devices (one active, one inactive)
    DeviceRegistry::create([
        'tenant_id' => $this->tenant->id,
        'device_id' => 'DEV001',
        'device_name' => 'Main Entrance Device',
        'device_type' => 'biometric',
        'is_active' => true,
    ]);

    DeviceRegistry::create([
        'tenant_id' => $this->tenant->id,
        'device_id' => 'DEV002',
        'device_name' => 'Inactive Device',
        'device_type' => 'biometric',
        'is_active' => false, // Inactive
    ]);

    $device1 = Device::on('tenant')->create([
        'device_id' => 'DEV001',
        'name' => 'Main Entrance',
        'location' => 'Building A',
        'ip_address' => '192.168.1.100',
        'capacity' => 5000,
        'is_active' => true,
    ]);

    Device::on('tenant')->create([
        'device_id' => 'DEV002',
        'name' => 'Back Door (Inactive)',
        'location' => 'Building B',
        'ip_address' => '192.168.1.101',
        'capacity' => 3000,
        'is_active' => false, // Inactive
    ]);

    $employee = Employee::on('tenant')->create([
        'custom_id' => 'EMP123456',
        'first_name' => 'John',
        'last_name' => 'Doe',
        'email' => 'john.doe@example.com',
        'department_id' => $this->department->id,
        'is_active' => true,
    ]);

    // Mock MQTT Client
    $mqttClient = Mockery::mock(MQTTClient::class);
    $this->app->instance(MQTTClient::class, $mqttClient);

    // Expect only one publish call (for active device)
    $mqttClient->shouldReceive('publish')->once();

    // Dispatch job
    $job = new SyncEmployeeToDevices($employee->id, $this->tenant->id);
    $job->handle($mqttClient, app(TenantDatabaseManager::class));

    // Verify only one enrollment was created (for active device)
    expect(DeviceEnrollment::on('tenant')->count())->toBe(1);

    $enrollment = DeviceEnrollment::on('tenant')
        ->where('employee_id', $employee->id)
        ->where('device_id', $device1->id)
        ->first();

    expect($enrollment)->not->toBeNull();
});

test('SyncEmployeeToDevices Job → updates existing enrollment status', function () {
    // Create device
    DeviceRegistry::create([
        'tenant_id' => $this->tenant->id,
        'device_id' => 'DEV001',
        'device_name' => 'Main Entrance Device',
        'device_type' => 'biometric',
        'is_active' => true,
    ]);

    $device = Device::on('tenant')->create([
        'device_id' => 'DEV001',
        'name' => 'Main Entrance',
        'location' => 'Building A',
        'ip_address' => '192.168.1.100',
        'capacity' => 5000,
        'is_active' => true,
    ]);

    $employee = Employee::on('tenant')->create([
        'custom_id' => 'EMP123456',
        'first_name' => 'John',
        'last_name' => 'Doe',
        'email' => 'john.doe@example.com',
        'department_id' => $this->department->id,
        'is_active' => true,
    ]);

    // Create existing enrollment with 'synced' status
    DeviceEnrollment::on('tenant')->create([
        'employee_id' => $employee->id,
        'device_id' => $device->id,
        'enrollment_status' => 'synced',
        'synced_at' => now()->subDay(),
    ]);

    // Mock MQTT Client
    $mqttClient = Mockery::mock(MQTTClient::class);
    $this->app->instance(MQTTClient::class, $mqttClient);
    $mqttClient->shouldReceive('publish')->once();

    // Dispatch job
    $job = new SyncEmployeeToDevices($employee->id, $this->tenant->id);
    $job->handle($mqttClient, app(TenantDatabaseManager::class));

    // Verify enrollment was updated to pending (waiting for device acknowledgement)
    expect(DeviceEnrollment::on('tenant')->count())->toBe(1);

    $enrollment = DeviceEnrollment::on('tenant')
        ->where('employee_id', $employee->id)
        ->where('device_id', $device->id)
        ->first();

    expect($enrollment->enrollment_status)->toBe('pending');
});

test('SyncEmployeeToDevices Job → handles employee not found gracefully', function () {
    // Mock MQTT Client
    $mqttClient = Mockery::mock(MQTTClient::class);
    $this->app->instance(MQTTClient::class, $mqttClient);

    // Should not attempt to publish if employee not found
    $mqttClient->shouldNotReceive('publish');

    // Dispatch job with non-existent employee ID
    Log::shouldReceive('channel')->andReturnSelf();
    Log::shouldReceive('warning')->once();

    $job = new SyncEmployeeToDevices(99999, $this->tenant->id);
    $job->handle($mqttClient, app(TenantDatabaseManager::class));

    expect(DeviceEnrollment::on('tenant')->count())->toBe(0);
});

test('SyncEmployeeToDevices Job → logs errors on MQTT publish failure', function () {
    // Create device
    DeviceRegistry::create([
        'tenant_id' => $this->tenant->id,
        'device_id' => 'DEV001',
        'device_name' => 'Main Entrance Device',
        'device_type' => 'biometric',
        'is_active' => true,
    ]);

    Device::on('tenant')->create([
        'device_id' => 'DEV001',
        'name' => 'Main Entrance',
        'location' => 'Building A',
        'ip_address' => '192.168.1.100',
        'capacity' => 5000,
        'is_active' => true,
    ]);

    $employee = Employee::on('tenant')->create([
        'custom_id' => 'EMP123456',
        'first_name' => 'John',
        'last_name' => 'Doe',
        'email' => 'john.doe@example.com',
        'department_id' => $this->department->id,
        'is_active' => true,
    ]);

    // Mock MQTT Client to throw exception
    $mqttClient = Mockery::mock(MQTTClient::class);
    $this->app->instance(MQTTClient::class, $mqttClient);
    $mqttClient->shouldReceive('publish')
        ->once()
        ->andThrow(new \Exception('MQTT connection failed'));

    // Expect error to be logged
    Log::shouldReceive('channel')->andReturnSelf();
    Log::shouldReceive('error')->once();

    // Dispatch job
    $job = new SyncEmployeeToDevices($employee->id, $this->tenant->id);
    $job->handle($mqttClient, app(TenantDatabaseManager::class));

    // Enrollment should still be created with 'failed' status
    $enrollment = DeviceEnrollment::on('tenant')
        ->where('employee_id', $employee->id)
        ->first();

    expect($enrollment)->not->toBeNull()
        ->and($enrollment->enrollment_status)->toBe('failed');
});
