<?php

use App\DTOs\StrangerEventDTO;
use App\Jobs\ProcessStrangerEvent;
use App\Models\DeviceRegistry;
use App\Models\Tenant;
use App\Models\Tenant\Device;
use App\Models\Tenant\StrangerLog;
use App\Services\PhotoStorageService;
use App\Services\Tenancy\TenantDatabaseManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Fake S3 storage
    Storage::fake('s3');

    // Clear queue
    Queue::fake();
});

describe('ProcessStrangerEvent Job', function () {
    test('processes stranger event and creates stranger log', function () {
        // Create tenant in central database
        $tenant = Tenant::factory()->create([
            'tenant_id' => 'test-tenant',
            'database_name' => 'tenant_test_stranger_event',
        ]);

        // Create device registry in central database
        $deviceRegistry = DeviceRegistry::create([
            'tenant_id' => $tenant->tenant_id,
            'device_id' => 'DEVICE001',
            'is_active' => true,
        ]);

        // Provision tenant database
        $manager = new TenantDatabaseManager;
        $tenantDTO = new \App\DTOs\Tenant(
            id: $tenant->tenant_id,
            company_name: $tenant->company_name,
            subdomain: $tenant->subdomain,
            domain: $tenant->domain,
            database_name: $tenant->database_name,
            database_host: config('database.connections.mysql.host'),
            subscription_plan: 'professional',
            max_employees: 100,
            max_devices: 10,
            is_active: true,
        );
        $manager->provisionTenant($tenantDTO);

        // Switch to tenant database
        config(['database.default' => 'tenant']);

        // Create device in tenant database
        $device = Device::create([
            'device_id' => 'DEVICE001',
            'name' => 'Main Entrance',
            'location' => 'Building A',
            'ip_address' => '192.168.1.100',
            'capacity' => 3000,
            'is_active' => true,
        ]);

        // Create stranger event DTO
        $event = new StrangerEventDTO(
            device_id: 'DEVICE001',
            detected_at: now(),
            photo_base64: base64_encode('fake-image-data'),
            similarity_score: 45.5,
            record_id: 'REC123456'
        );

        // Process the job
        $job = new ProcessStrangerEvent($event);
        $job->handle($manager, app(PhotoStorageService::class));

        // Restore default connection
        config(['database.default' => 'sqlite']);

        // Verify stranger log was created
        $log = StrangerLog::on('tenant')->where('device_id', $device->id)->first();

        expect($log)->not->toBeNull()
            ->and($log->photo_path)->toStartWith('strangers/photos/')
            ->and($log->match_status)->toBe('unreviewed');

        // Verify photo was uploaded to S3
        Storage::disk('s3')->assertExists($log->photo_path);

        // Cleanup - Drop test database
        $pdo = new \PDO(
            'mysql:host='.config('database.connections.mysql.host'),
            config('database.connections.mysql.username'),
            config('database.connections.mysql.password')
        );
        $pdo->exec('DROP DATABASE IF EXISTS tenant_test_stranger_event');
    })->skip('Requires tenant database setup');

    test('job is queued on attendance-default queue', function () {
        $event = new StrangerEventDTO(
            device_id: 'DEVICE001',
            detected_at: new DateTimeImmutable,
            photo_base64: base64_encode('fake-image-data'),
            similarity_score: 45.5,
            record_id: 'REC123456'
        );

        $job = new ProcessStrangerEvent($event);

        expect($job->queue)->toBe(env('QUEUE_DEFAULT', 'attendance-default'));
    });

    test('job has retry configuration', function () {
        $event = new StrangerEventDTO(
            device_id: 'DEVICE001',
            detected_at: new DateTimeImmutable,
            photo_base64: base64_encode('fake-image-data'),
            similarity_score: 45.5,
            record_id: 'REC123456'
        );

        $job = new ProcessStrangerEvent($event);

        expect($job->tries)->toBe(3)
            ->and($job->timeout)->toBe(60)
            ->and($job->backoff())->toBe([60, 300, 900]);
    });
});
