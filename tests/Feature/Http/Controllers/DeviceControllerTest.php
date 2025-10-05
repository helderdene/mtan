<?php

use App\Models\Tenant\Department;
use App\Models\Tenant\Device;
use App\Models\Tenant\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Run central database migrations
    Artisan::call('migrate', ['--path' => 'database/migrations/central']);

    // Create test tenant
    $tenant = \App\Models\Tenant::create([
        'id' => 'device_controller_test',
        'company_name' => 'Test Company',
        'subdomain' => 'test',
        'domain' => null,
        'database_name' => 'tenant_test_device_ctrl',
        'database_host' => env('DB_HOST', '127.0.0.1'),
        'subscription_plan' => 'professional',
        'max_employees' => 100,
        'max_devices' => 10,
        'is_active' => true,
    ]);

    // Provision tenant database
    $tenantDto = new \App\DTOs\Tenant(
        id: $tenant->id,
        company_name: $tenant->company_name,
        subdomain: $tenant->subdomain,
        domain: $tenant->domain,
        database_name: $tenant->database_name,
        database_host: $tenant->database_host,
        subscription_plan: $tenant->subscription_plan,
        max_employees: $tenant->max_employees,
        max_devices: $tenant->max_devices,
        is_active: $tenant->is_active,
    );

    $manager = new \App\Services\Tenancy\TenantDatabaseManager;
    $manager->provisionTenant($tenantDto);
    $manager->setupTenantConnection($tenantDto);

    // Create authenticated user
    $this->user = User::factory()->create(['role' => 'tenant_admin']);
});

afterEach(function () {
    // Drop test database
    $pdo = new \PDO(
        'mysql:host='.env('DB_HOST', '127.0.0.1'),
        env('DB_USERNAME', 'root'),
        env('DB_PASSWORD', '')
    );
    $pdo->exec('DROP DATABASE IF EXISTS tenant_test_device_ctrl');
});

describe('DeviceController', function () {
    test('index displays devices list', function () {
        $device1 = Device::on('tenant')->create([
            'device_id' => 'DEVICE001',
            'name' => 'Main Entrance',
            'location' => 'Building A',
            'ip_address' => '192.168.1.100',
            'capacity' => 3000,
            'is_active' => true,
        ]);

        $device2 = Device::on('tenant')->create([
            'device_id' => 'DEVICE002',
            'name' => 'Back Door',
            'location' => 'Building B',
            'ip_address' => '192.168.1.101',
            'capacity' => 1500,
            'is_active' => false,
        ]);

        $response = $this->actingAs($this->user)->get(route('devices.index'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('devices/Index')
            ->has('devices', 2)
            ->where('devices.0.device_id', 'DEVICE002') // Most recent first
            ->where('devices.1.device_id', 'DEVICE001')
        );
    });

    test('create displays device form', function () {
        $response = $this->actingAs($this->user)->get(route('devices.create'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('devices/Form')
        );
    });

    test('store creates new device', function () {
        $response = $this->actingAs($this->user)->post(route('devices.store'), [
            'device_id' => 'DEVICE003',
            'name' => 'Side Entrance',
            'location' => 'Building C',
            'ip_address' => '192.168.1.102',
            'capacity' => 2000,
            'is_active' => true,
        ]);

        $response->assertRedirect(route('devices.index'));
        $response->assertSessionHas('success', 'Device created successfully.');

        expect(Device::on('tenant')->where('device_id', 'DEVICE003')->exists())->toBeTrue();

        $device = Device::on('tenant')->where('device_id', 'DEVICE003')->first();
        expect($device->name)->toBe('Side Entrance');
        expect($device->capacity)->toBe(2000);
    });

    test('store validates required fields', function () {
        $response = $this->actingAs($this->user)->post(route('devices.store'), [
            'device_id' => '',
            'name' => '',
            'location' => '',
            'capacity' => '',
        ]);

        $response->assertSessionHasErrors(['device_id', 'name', 'location', 'capacity']);
    });

    test('store validates unique device_id', function () {
        Device::on('tenant')->create([
            'device_id' => 'DEVICE001',
            'name' => 'Test Device',
            'location' => 'Test Location',
            'ip_address' => '192.168.1.100',
            'capacity' => 3000,
        ]);

        $response = $this->actingAs($this->user)->post(route('devices.store'), [
            'device_id' => 'DEVICE001', // Duplicate
            'name' => 'Another Device',
            'location' => 'Another Location',
            'capacity' => 1500,
        ]);

        $response->assertSessionHasErrors(['device_id']);
    });

    test('store validates device_id format', function () {
        $response = $this->actingAs($this->user)->post(route('devices.store'), [
            'device_id' => 'device001', // Lowercase not allowed
            'name' => 'Test Device',
            'location' => 'Test Location',
            'capacity' => 3000,
        ]);

        $response->assertSessionHasErrors(['device_id']);
    });

    test('store validates ip address format', function () {
        $response = $this->actingAs($this->user)->post(route('devices.store'), [
            'device_id' => 'DEVICE001',
            'name' => 'Test Device',
            'location' => 'Test Location',
            'ip_address' => 'invalid-ip',
            'capacity' => 3000,
        ]);

        $response->assertSessionHasErrors(['ip_address']);
    });

    test('store validates capacity range', function () {
        $response = $this->actingAs($this->user)->post(route('devices.store'), [
            'device_id' => 'DEVICE001',
            'name' => 'Test Device',
            'location' => 'Test Location',
            'capacity' => 0, // Below minimum
        ]);

        $response->assertSessionHasErrors(['capacity']);

        $response = $this->actingAs($this->user)->post(route('devices.store'), [
            'device_id' => 'DEVICE001',
            'name' => 'Test Device',
            'location' => 'Test Location',
            'capacity' => 15000, // Above maximum
        ]);

        $response->assertSessionHasErrors(['capacity']);
    });

    test('edit displays device edit form', function () {
        $device = Device::on('tenant')->create([
            'device_id' => 'DEVICE001',
            'name' => 'Main Entrance',
            'location' => 'Building A',
            'ip_address' => '192.168.1.100',
            'capacity' => 3000,
        ]);

        $response = $this->actingAs($this->user)->get(route('devices.edit', $device->id));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('devices/Form')
            ->has('device')
            ->where('device.device_id', 'DEVICE001')
        );
    });

    test('update modifies existing device', function () {
        $device = Device::on('tenant')->create([
            'device_id' => 'DEVICE001',
            'name' => 'Main Entrance',
            'location' => 'Building A',
            'ip_address' => '192.168.1.100',
            'capacity' => 3000,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->user)->put(route('devices.update', $device->id), [
            'name' => 'Front Gate',
            'location' => 'Building A - Updated',
            'ip_address' => '192.168.1.150',
            'capacity' => 5000,
            'is_active' => false,
        ]);

        $response->assertRedirect(route('devices.index'));
        $response->assertSessionHas('success', 'Device updated successfully.');

        $device->refresh();
        expect($device->name)->toBe('Front Gate');
        expect($device->capacity)->toBe(5000);
        expect($device->is_active)->toBeFalse();
    });

    test('update cannot change device_id', function () {
        $device = Device::on('tenant')->create([
            'device_id' => 'DEVICE001',
            'name' => 'Main Entrance',
            'location' => 'Building A',
            'ip_address' => '192.168.1.100',
            'capacity' => 3000,
        ]);

        // Device ID should not be in the update request
        $response = $this->actingAs($this->user)->put(route('devices.update', $device->id), [
            'name' => 'Updated Name',
            'location' => 'Updated Location',
            'capacity' => 4000,
        ]);

        $device->refresh();
        expect($device->device_id)->toBe('DEVICE001'); // Should remain unchanged
    });

    test('destroy deletes device without attendance records', function () {
        $device = Device::on('tenant')->create([
            'device_id' => 'DEVICE001',
            'name' => 'Main Entrance',
            'location' => 'Building A',
            'ip_address' => '192.168.1.100',
            'capacity' => 3000,
        ]);

        $response = $this->actingAs($this->user)->delete(route('devices.destroy', $device->id));

        $response->assertRedirect(route('devices.index'));
        $response->assertSessionHas('success', 'Device deleted successfully.');

        expect(Device::on('tenant')->where('id', $device->id)->exists())->toBeFalse();
    });

    test('destroy prevents deletion of device with attendance records', function () {
        $department = Department::on('tenant')->create(['name' => 'Engineering']);

        $employee = Employee::on('tenant')->create([
            'custom_id' => 'EMP001',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
            'department_id' => $department->id,
        ]);

        $device = Device::on('tenant')->create([
            'device_id' => 'DEVICE001',
            'name' => 'Main Entrance',
            'location' => 'Building A',
            'ip_address' => '192.168.1.100',
            'capacity' => 3000,
        ]);

        // Create attendance record
        \App\Models\Tenant\AttendanceRecord::on('tenant')->create([
            'employee_id' => $employee->id,
            'device_id' => $device->id,
            'recorded_at' => now(),
            'direction' => 'check-in',
        ]);

        $response = $this->actingAs($this->user)->delete(route('devices.destroy', $device->id));

        $response->assertRedirect(route('devices.index'));
        $response->assertSessionHas('error');

        expect(Device::on('tenant')->where('id', $device->id)->exists())->toBeTrue();
    });

    test('requires authentication', function () {
        $response = $this->get(route('devices.index'));
        $response->assertRedirect(route('login'));
    });
});
