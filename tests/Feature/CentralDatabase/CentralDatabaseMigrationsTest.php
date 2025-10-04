<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    // Use central database connection for these tests
    config(['database.default' => 'central']);
});

afterEach(function () {
    // Reset to default connection
    config(['database.default' => 'sqlite']);
});

describe('Central Database Migrations', function () {

    test('tenants table exists with correct structure', function () {
        expect(Schema::connection('central')->hasTable('tenants'))->toBeTrue();

        $columns = Schema::connection('central')->getColumnListing('tenants');

        expect($columns)->toContain('id');
        expect($columns)->toContain('company_name');
        expect($columns)->toContain('domain');
        expect($columns)->toContain('subdomain');
        expect($columns)->toContain('database_name');
        expect($columns)->toContain('database_host');
        expect($columns)->toContain('subscription_plan');
        expect($columns)->toContain('max_employees');
        expect($columns)->toContain('max_devices');
        expect($columns)->toContain('features');
        expect($columns)->toContain('is_active');
        expect($columns)->toContain('trial_ends_at');
        expect($columns)->toContain('subscription_starts_at');
        expect($columns)->toContain('subscription_ends_at');
        expect($columns)->toContain('created_at');
        expect($columns)->toContain('updated_at');
    });

    test('tenants table has correct indexes', function () {
        $indexes = DB::connection('central')
            ->select('SHOW INDEX FROM tenants');

        $indexNames = collect($indexes)->pluck('Key_name')->unique()->values()->all();

        expect($indexNames)->toContain('tenants_domain_unique');
        expect($indexNames)->toContain('tenants_subdomain_unique');
    });

    test('device_registry table exists with correct structure', function () {
        expect(Schema::connection('central')->hasTable('device_registry'))->toBeTrue();

        $columns = Schema::connection('central')->getColumnListing('device_registry');

        expect($columns)->toContain('id');
        expect($columns)->toContain('tenant_id');
        expect($columns)->toContain('device_id');
        expect($columns)->toContain('device_name');
        expect($columns)->toContain('device_type');
        expect($columns)->toContain('location');
        expect($columns)->toContain('ip_address');
        expect($columns)->toContain('mac_address');
        expect($columns)->toContain('firmware_version');
        expect($columns)->toContain('is_active');
        expect($columns)->toContain('last_seen_at');
        expect($columns)->toContain('registered_at');
        expect($columns)->toContain('metadata');
        expect($columns)->toContain('created_at');
        expect($columns)->toContain('updated_at');
    });

    test('device_registry table has foreign key to tenants', function () {
        $foreignKeys = DB::connection('central')
            ->select("SELECT CONSTRAINT_NAME, REFERENCED_TABLE_NAME
                     FROM information_schema.KEY_COLUMN_USAGE
                     WHERE TABLE_SCHEMA = DATABASE()
                     AND TABLE_NAME = 'device_registry'
                     AND REFERENCED_TABLE_NAME IS NOT NULL");

        expect($foreignKeys)->not->toBeEmpty();
        expect(collect($foreignKeys)->pluck('REFERENCED_TABLE_NAME'))->toContain('tenants');
    });

    test('super_admins table exists with correct structure', function () {
        expect(Schema::connection('central')->hasTable('super_admins'))->toBeTrue();

        $columns = Schema::connection('central')->getColumnListing('super_admins');

        expect($columns)->toContain('id');
        expect($columns)->toContain('name');
        expect($columns)->toContain('email');
        expect($columns)->toContain('password');
        expect($columns)->toContain('email_verified_at');
        expect($columns)->toContain('two_factor_secret');
        expect($columns)->toContain('two_factor_enabled');
        expect($columns)->toContain('last_login_at');
        expect($columns)->toContain('last_login_ip');
        expect($columns)->toContain('is_active');
        expect($columns)->toContain('remember_token');
        expect($columns)->toContain('created_at');
        expect($columns)->toContain('updated_at');
    });

    test('mqtt_broker_configs table exists with correct structure', function () {
        expect(Schema::connection('central')->hasTable('mqtt_broker_configs'))->toBeTrue();

        $columns = Schema::connection('central')->getColumnListing('mqtt_broker_configs');

        expect($columns)->toContain('id');
        expect($columns)->toContain('name');
        expect($columns)->toContain('host');
        expect($columns)->toContain('port');
        expect($columns)->toContain('protocol');
        expect($columns)->toContain('username');
        expect($columns)->toContain('password');
        expect($columns)->toContain('client_id');
        expect($columns)->toContain('clean_session');
        expect($columns)->toContain('keep_alive');
        expect($columns)->toContain('qos');
        expect($columns)->toContain('is_primary');
        expect($columns)->toContain('is_active');
        expect($columns)->toContain('priority');
        expect($columns)->toContain('max_connections');
        expect($columns)->toContain('certificate_path');
        expect($columns)->toContain('metadata');
        expect($columns)->toContain('created_at');
        expect($columns)->toContain('updated_at');
    });

    test('tenant_usage_metrics table exists with correct structure', function () {
        expect(Schema::connection('central')->hasTable('tenant_usage_metrics'))->toBeTrue();

        $columns = Schema::connection('central')->getColumnListing('tenant_usage_metrics');

        expect($columns)->toContain('id');
        expect($columns)->toContain('tenant_id');
        expect($columns)->toContain('metric_date');
        expect($columns)->toContain('total_employees');
        expect($columns)->toContain('active_employees');
        expect($columns)->toContain('total_devices');
        expect($columns)->toContain('active_devices');
        expect($columns)->toContain('attendance_records_count');
        expect($columns)->toContain('storage_used_mb');
        expect($columns)->toContain('api_calls_count');
        expect($columns)->toContain('webhook_calls_count');
        expect($columns)->toContain('created_at');
    });

    test('can insert and retrieve tenant record', function () {
        $tenantId = (string) \Illuminate\Support\Str::uuid();

        DB::connection('central')->table('tenants')->insert([
            'id' => $tenantId,
            'company_name' => 'Test Company',
            'domain' => 'test.example.com',
            'subdomain' => 'test',
            'database_name' => 'tenant_test',
            'database_host' => '127.0.0.1',
            'subscription_plan' => 'trial',
            'max_employees' => 50,
            'max_devices' => 5,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $tenant = DB::connection('central')->table('tenants')->where('id', $tenantId)->first();

        expect($tenant)->not->toBeNull();
        expect($tenant->company_name)->toBe('Test Company');
        expect($tenant->domain)->toBe('test.example.com');
        expect($tenant->is_active)->toBe(1);
    });

    test('can insert and retrieve device registry record', function () {
        $tenantId = (string) \Illuminate\Support\Str::uuid();

        // Insert tenant first
        DB::connection('central')->table('tenants')->insert([
            'id' => $tenantId,
            'company_name' => 'Test Company',
            'domain' => 'test2.example.com',
            'subdomain' => 'test2',
            'database_name' => 'tenant_test2',
            'database_host' => '127.0.0.1',
            'subscription_plan' => 'trial',
            'max_employees' => 50,
            'max_devices' => 5,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Insert device
        DB::connection('central')->table('device_registry')->insert([
            'tenant_id' => $tenantId,
            'device_id' => 'DEVICE001',
            'device_name' => 'Main Entrance',
            'device_type' => 'facial_recognition',
            'is_active' => true,
            'registered_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $device = DB::connection('central')->table('device_registry')->where('device_id', 'DEVICE001')->first();

        expect($device)->not->toBeNull();
        expect($device->device_name)->toBe('Main Entrance');
        expect($device->tenant_id)->toBe($tenantId);
    });

    test('device_registry cascades delete when tenant is deleted', function () {
        $tenantId = (string) \Illuminate\Support\Str::uuid();

        // Insert tenant
        DB::connection('central')->table('tenants')->insert([
            'id' => $tenantId,
            'company_name' => 'Test Company 3',
            'domain' => 'test3.example.com',
            'subdomain' => 'test3',
            'database_name' => 'tenant_test3',
            'database_host' => '127.0.0.1',
            'subscription_plan' => 'trial',
            'max_employees' => 50,
            'max_devices' => 5,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Insert device
        DB::connection('central')->table('device_registry')->insert([
            'tenant_id' => $tenantId,
            'device_id' => 'DEVICE_CASCADE',
            'device_name' => 'Cascade Test',
            'device_type' => 'facial_recognition',
            'is_active' => true,
            'registered_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Delete tenant
        DB::connection('central')->table('tenants')->where('id', $tenantId)->delete();

        // Verify device was also deleted
        $device = DB::connection('central')->table('device_registry')->where('device_id', 'DEVICE_CASCADE')->first();

        expect($device)->toBeNull();
    });
});
