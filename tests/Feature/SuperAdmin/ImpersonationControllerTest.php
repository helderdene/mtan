<?php

use App\Models\ImpersonationLog;
use App\Models\SuperAdmin;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Run central database migrations
    Artisan::call('migrate:fresh', ['--database' => 'central', '--path' => 'database/migrations/central', '--force' => true]);

    // Define domain for tests
    $this->domain = 'mtan.test';
    $this->adminHost = 'admin.'.$this->domain;

    // Create test super admin
    $this->superAdmin = SuperAdmin::factory()->create([
        'email' => 'admin@example.com',
        'is_active' => true,
    ]);

    // Create test tenant
    $this->tenant = Tenant::create([
        'id' => 'tenant_001',
        'company_name' => 'Test Company',
        'domain' => 'testcompany',
        'subdomain' => 'testcompany',
        'database_name' => 'tenant_testcompany',
        'database_host' => 'localhost',
        'is_active' => true,
        'subscription_plan' => 'professional',
    ]);

    // Create test user (in the application, not in tenant DB for now)
    $this->user = User::factory()->create([
        'email' => 'user@testcompany.com',
        'name' => 'Test User',
    ]);
});

describe('Impersonation Start', function () {
    test('super admin can start impersonating a tenant user', function () {
        $this->actingAs($this->superAdmin, 'super-admin');

        $response = $this->post("http://{$this->adminHost}/tenants/{$this->tenant->id}/impersonate/{$this->user->id}");

        $response->assertRedirect();
        expect(session()->has('impersonating'))->toBeTrue();
        expect(session('impersonating.super_admin_id'))->toBe($this->superAdmin->id);
        expect(session('impersonating.tenant_id'))->toBe($this->tenant->id);
        expect(session('impersonating.user_id'))->toBe($this->user->id);
    });

    test('impersonation creates audit log entry', function () {
        $this->actingAs($this->superAdmin, 'super-admin');

        $this->post("http://{$this->adminHost}/tenants/{$this->tenant->id}/impersonate/{$this->user->id}");

        expect(ImpersonationLog::count())->toBe(1);

        $log = ImpersonationLog::first();
        expect($log->super_admin_id)->toBe($this->superAdmin->id);
        expect($log->tenant_id)->toBe($this->tenant->id);
        expect($log->user_id)->toBe($this->user->id);
        expect($log->user_email)->toBe($this->user->email);
        expect($log->started_at)->not->toBeNull();
        expect($log->ended_at)->toBeNull();
    });

    test('impersonation stores IP address and user agent', function () {
        $this->actingAs($this->superAdmin, 'super-admin');

        $this->withServerVariables([
            'REMOTE_ADDR' => '192.168.1.100',
            'HTTP_USER_AGENT' => 'Mozilla/5.0 Test Browser',
        ])->post("http://{$this->adminHost}/tenants/{$this->tenant->id}/impersonate/{$this->user->id}");

        $log = ImpersonationLog::first();
        expect($log->ip_address)->toBe('192.168.1.100');
        expect($log->user_agent)->toBe('Mozilla/5.0 Test Browser');
    });

    test('guest cannot start impersonation', function () {
        $response = $this->post("http://{$this->adminHost}/tenants/{$this->tenant->id}/impersonate/{$this->user->id}");

        $response->assertRedirect("http://{$this->adminHost}/admin-login");
        expect(session()->has('impersonating'))->toBeFalse();
    });

    test('inactive super admin cannot start impersonation', function () {
        $inactiveSuperAdmin = SuperAdmin::factory()->create([
            'is_active' => false,
        ]);

        $this->actingAs($inactiveSuperAdmin, 'super-admin');

        $response = $this->post("http://{$this->adminHost}/tenants/{$this->tenant->id}/impersonate/{$this->user->id}");

        $response->assertRedirect();
        expect(session()->has('impersonating'))->toBeFalse();
    });

    test('cannot impersonate inactive tenant', function () {
        $this->tenant->update(['is_active' => false]);

        $this->actingAs($this->superAdmin, 'super-admin');

        $response = $this->post("http://{$this->adminHost}/tenants/{$this->tenant->id}/impersonate/{$this->user->id}");

        $response->assertStatus(403);
        expect(session()->has('impersonating'))->toBeFalse();
    });

    test('impersonation redirects to tenant dashboard', function () {
        $this->actingAs($this->superAdmin, 'super-admin');

        $response = $this->post("http://{$this->adminHost}/tenants/{$this->tenant->id}/impersonate/{$this->user->id}");

        // Should redirect to main app (tenant domain)
        $response->assertRedirect();
        $redirectUrl = $response->headers->get('Location');
        expect($redirectUrl)->toContain('dashboard');
    });
});

describe('Impersonation Exit', function () {
    beforeEach(function () {
        // Create impersonation log first
        $this->impersonationLog = ImpersonationLog::create([
            'super_admin_id' => $this->superAdmin->id,
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'user_email' => $this->user->email,
            'started_at' => now(),
            'ip_address' => '127.0.0.1',
        ]);

        // Set up impersonation session with log_id
        session([
            'impersonating' => [
                'super_admin_id' => $this->superAdmin->id,
                'tenant_id' => $this->tenant->id,
                'user_id' => $this->user->id,
                'started_at' => now()->toDateTimeString(),
                'log_id' => $this->impersonationLog->id,
            ],
        ]);
    });

    test('can exit impersonation', function () {
        $this->actingAs($this->user);

        $response = $this->post('/exit-impersonation');

        $response->assertRedirect();
        expect(session()->has('impersonating'))->toBeFalse();
    });

    test('exit impersonation updates audit log', function () {
        $this->actingAs($this->user);

        $this->post('/exit-impersonation');

        $this->impersonationLog->refresh();
        expect($this->impersonationLog->ended_at)->not->toBeNull();
        expect($this->impersonationLog->exit_reason)->toBe('manual');
    });

    test('exit impersonation redirects to super admin dashboard', function () {
        $this->actingAs($this->user);

        $response = $this->post('/exit-impersonation');

        $response->assertRedirect("http://{$this->adminHost}/dashboard");
    });

    test('exit impersonation without active session does nothing', function () {
        session()->forget('impersonating');

        $this->actingAs($this->user);

        $response = $this->post('/exit-impersonation');

        $response->assertRedirect('/');
    });

    test('exit impersonation calculates session duration', function () {
        $this->actingAs($this->user);

        // Simulate 5 minutes of impersonation
        $this->impersonationLog->update(['started_at' => now()->subMinutes(5)]);

        $this->post('/exit-impersonation');

        $this->impersonationLog->refresh();
        $duration = $this->impersonationLog->duration();
        expect($duration)->toBeGreaterThan(290); // ~5 minutes in seconds
        expect($duration)->toBeLessThan(310);
    });
});

describe('Impersonation State', function () {
    test('impersonation state is accessible in session', function () {
        session([
            'impersonating' => [
                'super_admin_id' => $this->superAdmin->id,
                'tenant_id' => $this->tenant->id,
                'user_id' => $this->user->id,
            ],
        ]);

        expect(session('impersonating.super_admin_id'))->toBe($this->superAdmin->id);
        expect(session('impersonating.tenant_id'))->toBe($this->tenant->id);
        expect(session('impersonating.user_id'))->toBe($this->user->id);
    });

    test('can check if currently impersonating', function () {
        expect(session()->has('impersonating'))->toBeFalse();

        session(['impersonating' => ['super_admin_id' => 1]]);

        expect(session()->has('impersonating'))->toBeTrue();
    });
});
