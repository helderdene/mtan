<?php

use App\Models\DeviceRegistry;
use App\Models\SuperAdmin;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Run central database migrations
    Artisan::call('migrate', ['--path' => 'database/migrations/central', '--force' => true]);

    // Create super admin user
    $this->superAdmin = SuperAdmin::factory()->create([
        'is_active' => true,
    ]);

    // Create test tenants with valid UUIDs
    $this->tenant1 = Tenant::create([
        'id' => (string) Str::uuid(),
        'company_name' => 'Company A',
        'subdomain' => 'company-a',
        'domain' => null,
        'database_name' => 'tenant_company_a',
        'database_host' => '127.0.0.1',
        'subscription_plan' => 'professional',
        'max_employees' => 100,
        'max_devices' => 10,
        'is_active' => true,
    ]);

    $this->tenant2 = Tenant::create([
        'id' => (string) Str::uuid(),
        'company_name' => 'Company B',
        'subdomain' => 'company-b',
        'domain' => null,
        'database_name' => 'tenant_company_b',
        'database_host' => '127.0.0.1',
        'subscription_plan' => 'enterprise',
        'max_employees' => 500,
        'max_devices' => 50,
        'is_active' => true,
    ]);
});

describe('SuperAdmin\\DeviceRegistryController', function () {
    test('index displays device registry list', function () {
        // Create device registrations
        DeviceRegistry::create([
            'tenant_id' => $this->tenant1->id,
            'device_id' => 'DEV001',
            'device_name' => 'Main Entrance',
            'device_type' => 'facial_recognition',
            'location' => 'Building A - Entrance',
            'ip_address' => '192.168.1.100',
            'is_active' => true,
        ]);

        DeviceRegistry::create([
            'tenant_id' => $this->tenant2->id,
            'device_id' => 'DEV002',
            'device_name' => 'Back Office',
            'device_type' => 'facial_recognition',
            'location' => 'Building B - Office',
            'ip_address' => '192.168.1.101',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->superAdmin, 'super_admin')
            ->get(route('super-admin.device-registry.index'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('super-admin/device-registry/Index')
            ->has('devices.data', 2)
        );
    });

    test('index can filter devices by tenant', function () {
        DeviceRegistry::create([
            'tenant_id' => $this->tenant1->id,
            'device_id' => 'DEV001',
            'device_name' => 'Main Entrance',
            'device_type' => 'facial_recognition',
            'is_active' => true,
        ]);

        DeviceRegistry::create([
            'tenant_id' => $this->tenant2->id,
            'device_id' => 'DEV002',
            'device_name' => 'Back Office',
            'device_type' => 'facial_recognition',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->superAdmin, 'super_admin')
            ->get(route('super-admin.device-registry.index', ['tenant_id' => $this->tenant1->id]));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('super-admin/device-registry/Index')
            ->has('devices.data', 1)
            ->where('devices.data.0.device_id', 'DEV001')
        );
    });

    test('create displays device registration form', function () {
        $response = $this->actingAs($this->superAdmin, 'super_admin')
            ->get(route('super-admin.device-registry.create'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('super-admin/device-registry/Form')
            ->has('tenants')
        );
    });

    test('store creates new device registration', function () {
        $response = $this->actingAs($this->superAdmin, 'super_admin')
            ->post(route('super-admin.device-registry.store'), [
                'tenant_id' => $this->tenant1->id,
                'device_id' => 'DEV003',
                'device_name' => 'Side Entrance',
                'device_type' => 'facial_recognition',
                'location' => 'Building A - Side Door',
                'ip_address' => '192.168.1.102',
                'mac_address' => 'AA:BB:CC:DD:EE:FF',
                'firmware_version' => '1.0.0',
                'is_active' => true,
            ]);

        $response->assertRedirect(route('super-admin.device-registry.index'));
        $response->assertSessionHas('success');

        expect(DeviceRegistry::where('device_id', 'DEV003')->exists())->toBeTrue();

        $device = DeviceRegistry::where('device_id', 'DEV003')->first();
        expect($device->device_name)->toBe('Side Entrance');
        expect($device->tenant_id)->toBe($this->tenant1->id);
    });

    test('store validates required fields', function () {
        $response = $this->actingAs($this->superAdmin, 'super_admin')
            ->post(route('super-admin.device-registry.store'), [
                'tenant_id' => '',
                'device_id' => '',
                'device_name' => '',
                'device_type' => '',
            ]);

        $response->assertSessionHasErrors(['tenant_id', 'device_id', 'device_name', 'device_type']);
    });

    test('store validates unique device_id', function () {
        DeviceRegistry::create([
            'tenant_id' => $this->tenant1->id,
            'device_id' => 'DEV001',
            'device_name' => 'Existing Device',
            'device_type' => 'facial_recognition',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->superAdmin, 'super_admin')
            ->post(route('super-admin.device-registry.store'), [
                'tenant_id' => $this->tenant2->id,
                'device_id' => 'DEV001',
                'device_name' => 'New Device',
                'device_type' => 'facial_recognition',
            ]);

        $response->assertSessionHasErrors(['device_id']);
    });

    test('store validates ip address format', function () {
        $response = $this->actingAs($this->superAdmin, 'super_admin')
            ->post(route('super-admin.device-registry.store'), [
                'tenant_id' => $this->tenant1->id,
                'device_id' => 'DEV003',
                'device_name' => 'Test Device',
                'device_type' => 'facial_recognition',
                'ip_address' => 'invalid-ip',
            ]);

        $response->assertSessionHasErrors(['ip_address']);
    });

    test('store validates mac address format', function () {
        $response = $this->actingAs($this->superAdmin, 'super_admin')
            ->post(route('super-admin.device-registry.store'), [
                'tenant_id' => $this->tenant1->id,
                'device_id' => 'DEV003',
                'device_name' => 'Test Device',
                'device_type' => 'facial_recognition',
                'mac_address' => 'invalid-mac',
            ]);

        $response->assertSessionHasErrors(['mac_address']);
    });

    test('show displays device details', function () {
        $device = DeviceRegistry::create([
            'tenant_id' => $this->tenant1->id,
            'device_id' => 'DEV001',
            'device_name' => 'Main Entrance',
            'device_type' => 'facial_recognition',
            'location' => 'Building A - Entrance',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->superAdmin, 'super_admin')
            ->get(route('super-admin.device-registry.show', $device->id));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('super-admin/device-registry/Show')
            ->has('device')
            ->where('device.device_id', 'DEV001')
        );
    });

    test('edit displays device edit form', function () {
        $device = DeviceRegistry::create([
            'tenant_id' => $this->tenant1->id,
            'device_id' => 'DEV001',
            'device_name' => 'Main Entrance',
            'device_type' => 'facial_recognition',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->superAdmin, 'super_admin')
            ->get(route('super-admin.device-registry.edit', $device->id));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('super-admin/device-registry/Form')
            ->has('device')
            ->has('tenants')
        );
    });

    test('update modifies existing device', function () {
        $device = DeviceRegistry::create([
            'tenant_id' => $this->tenant1->id,
            'device_id' => 'DEV001',
            'device_name' => 'Main Entrance',
            'device_type' => 'facial_recognition',
            'location' => 'Building A',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->superAdmin, 'super_admin')
            ->put(route('super-admin.device-registry.update', $device->id), [
                'device_name' => 'Updated Main Entrance',
                'device_type' => 'facial_recognition',
                'location' => 'Building A - Main Door',
                'ip_address' => '192.168.1.200',
                'is_active' => false,
            ]);

        $response->assertRedirect(route('super-admin.device-registry.index'));
        $response->assertSessionHas('success');

        $device->refresh();
        expect($device->device_name)->toBe('Updated Main Entrance');
        expect($device->location)->toBe('Building A - Main Door');
        expect($device->ip_address)->toBe('192.168.1.200');
        expect($device->is_active)->toBeFalse();
    });

    test('update cannot change device_id', function () {
        $device = DeviceRegistry::create([
            'tenant_id' => $this->tenant1->id,
            'device_id' => 'DEV001',
            'device_name' => 'Main Entrance',
            'device_type' => 'facial_recognition',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->superAdmin, 'super_admin')
            ->put(route('super-admin.device-registry.update', $device->id), [
                'device_id' => 'DEV999',
                'device_name' => 'Updated Name',
                'device_type' => 'facial_recognition',
            ]);

        $device->refresh();
        expect($device->device_id)->toBe('DEV001');
    });

    test('destroy deletes device registration', function () {
        $device = DeviceRegistry::create([
            'tenant_id' => $this->tenant1->id,
            'device_id' => 'DEV001',
            'device_name' => 'Main Entrance',
            'device_type' => 'facial_recognition',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->superAdmin, 'super_admin')
            ->delete(route('super-admin.device-registry.destroy', $device->id));

        $response->assertRedirect(route('super-admin.device-registry.index'));
        $response->assertSessionHas('success');

        expect(DeviceRegistry::find($device->id))->toBeNull();
    });

    test('requires super admin authentication', function () {
        $response = $this->get(route('super-admin.device-registry.index'));
        $response->assertRedirect(route('super-admin.login'));
    });

    test('inactive super admin cannot access', function () {
        $inactiveSuperAdmin = SuperAdmin::factory()->inactive()->create();

        $response = $this->actingAs($inactiveSuperAdmin, 'super_admin')
            ->get(route('super-admin.device-registry.index'));

        $response->assertRedirect(route('super-admin.login'));
        $response->assertSessionHas('error');
    });
});
