<?php

use App\Models\ImpersonationLog;
use App\Models\SuperAdmin;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Run central database migrations (will use SQLite in memory for tests)
    Artisan::call('migrate:fresh', ['--database' => 'central', '--path' => 'database/migrations/central', '--force' => true]);

    // Create super admin user
    $this->superAdmin = SuperAdmin::factory()->create([
        'is_active' => true,
    ]);

    // Create a tenant
    $this->tenant = Tenant::create([
        'id' => 'tenant_001',
        'company_name' => 'Test Company',
        'subdomain' => 'test',
        'domain' => null,
        'database_name' => 'tenant_test',
        'database_host' => '127.0.0.1',
        'subscription_plan' => 'professional',
        'max_employees' => 100,
        'max_devices' => 10,
        'is_active' => true,
    ]);
});

describe('ImpersonationLog Model', function () {
    test('can create impersonation log', function () {
        $log = ImpersonationLog::create([
            'super_admin_id' => $this->superAdmin->id,
            'tenant_id' => $this->tenant->id,
            'user_id' => 1,
            'user_email' => 'user@test.com',
            'started_at' => now(),
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Mozilla/5.0',
        ]);

        expect($log->exists())->toBeTrue();
        expect($log->super_admin_id)->toBe($this->superAdmin->id);
        expect($log->tenant_id)->toBe($this->tenant->id);
        expect($log->user_email)->toBe('user@test.com');
    });

    test('belongs to super admin', function () {
        $log = ImpersonationLog::create([
            'super_admin_id' => $this->superAdmin->id,
            'tenant_id' => $this->tenant->id,
            'user_id' => 1,
            'user_email' => 'user@test.com',
            'started_at' => now(),
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Mozilla/5.0',
        ]);

        expect($log->superAdmin)->toBeInstanceOf(SuperAdmin::class);
        expect($log->superAdmin->id)->toBe($this->superAdmin->id);
    });

    test('belongs to tenant', function () {
        $log = ImpersonationLog::create([
            'super_admin_id' => $this->superAdmin->id,
            'tenant_id' => $this->tenant->id,
            'user_id' => 1,
            'user_email' => 'user@test.com',
            'started_at' => now(),
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Mozilla/5.0',
        ]);

        expect($log->tenant)->toBeInstanceOf(Tenant::class);
        expect($log->tenant->id)->toBe($this->tenant->id);
    });

    test('super admin has many impersonation logs', function () {
        ImpersonationLog::create([
            'super_admin_id' => $this->superAdmin->id,
            'tenant_id' => $this->tenant->id,
            'user_id' => 1,
            'user_email' => 'user1@test.com',
            'started_at' => now(),
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Mozilla/5.0',
        ]);

        ImpersonationLog::create([
            'super_admin_id' => $this->superAdmin->id,
            'tenant_id' => $this->tenant->id,
            'user_id' => 2,
            'user_email' => 'user2@test.com',
            'started_at' => now(),
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Mozilla/5.0',
        ]);

        expect($this->superAdmin->impersonationLogs()->count())->toBe(2);
    });

    test('can scope active impersonations', function () {
        // Create ended impersonation
        ImpersonationLog::create([
            'super_admin_id' => $this->superAdmin->id,
            'tenant_id' => $this->tenant->id,
            'user_id' => 1,
            'user_email' => 'user1@test.com',
            'started_at' => now()->subHour(),
            'ended_at' => now()->subMinutes(30),
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Mozilla/5.0',
            'exit_reason' => 'manual',
        ]);

        // Create active impersonation
        ImpersonationLog::create([
            'super_admin_id' => $this->superAdmin->id,
            'tenant_id' => $this->tenant->id,
            'user_id' => 2,
            'user_email' => 'user2@test.com',
            'started_at' => now(),
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Mozilla/5.0',
        ]);

        expect(ImpersonationLog::active()->count())->toBe(1);
        expect(ImpersonationLog::active()->first()->user_email)->toBe('user2@test.com');
    });

    test('can end impersonation session', function () {
        $log = ImpersonationLog::create([
            'super_admin_id' => $this->superAdmin->id,
            'tenant_id' => $this->tenant->id,
            'user_id' => 1,
            'user_email' => 'user@test.com',
            'started_at' => now(),
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Mozilla/5.0',
        ]);

        expect($log->ended_at)->toBeNull();

        $log->update([
            'ended_at' => now(),
            'exit_reason' => 'manual',
        ]);

        expect($log->ended_at)->not->toBeNull();
        expect($log->exit_reason)->toBe('manual');
    });

    test('calculates session duration', function () {
        $startTime = now()->subMinutes(30);
        $endTime = now();

        $log = ImpersonationLog::create([
            'super_admin_id' => $this->superAdmin->id,
            'tenant_id' => $this->tenant->id,
            'user_id' => 1,
            'user_email' => 'user@test.com',
            'started_at' => $startTime,
            'ended_at' => $endTime,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Mozilla/5.0',
            'exit_reason' => 'manual',
        ]);

        $duration = $log->duration();

        // Duration should be approximately 30 minutes (1800 seconds)
        expect($duration)->toBeGreaterThanOrEqual(1790);
        expect($duration)->toBeLessThanOrEqual(1810);
    });

    test('requires super_admin_id', function () {
        $this->expectException(\Illuminate\Database\QueryException::class);

        ImpersonationLog::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => 1,
            'user_email' => 'user@test.com',
            'started_at' => now(),
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Mozilla/5.0',
        ]);
    });

    test('requires tenant_id', function () {
        $this->expectException(\Illuminate\Database\QueryException::class);

        ImpersonationLog::create([
            'super_admin_id' => $this->superAdmin->id,
            'user_id' => 1,
            'user_email' => 'user@test.com',
            'started_at' => now(),
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Mozilla/5.0',
        ]);
    });

    test('stores ip address and user agent', function () {
        $log = ImpersonationLog::create([
            'super_admin_id' => $this->superAdmin->id,
            'tenant_id' => $this->tenant->id,
            'user_id' => 1,
            'user_email' => 'user@test.com',
            'started_at' => now(),
            'ip_address' => '192.168.1.100',
            'user_agent' => 'Chrome/120.0.0.0',
        ]);

        expect($log->ip_address)->toBe('192.168.1.100');
        expect($log->user_agent)->toBe('Chrome/120.0.0.0');
    });
});
