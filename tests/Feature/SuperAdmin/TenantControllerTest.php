<?php

use App\Models\SuperAdmin;
use App\Models\Tenant;
use App\Services\Tenancy\TenantDatabaseManager;
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
});

describe('SuperAdmin\\TenantController', function () {
    test('index displays tenants list', function () {
        // Create tenants
        Tenant::create([
            'id' => 'tenant_001',
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

        Tenant::create([
            'id' => 'tenant_002',
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

        $response = $this->actingAs($this->superAdmin, 'super_admin')
            ->get(route('super-admin.tenants.index'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('super-admin/tenants/Index')
            ->has('tenants.data', 2)
        );
    });

    test('create displays tenant form', function () {
        $response = $this->actingAs($this->superAdmin, 'super_admin')
            ->get(route('super-admin.tenants.create'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('super-admin/tenants/Form')
        );
    });

    test('store creates new tenant', function () {
        $response = $this->actingAs($this->superAdmin, 'super_admin')
            ->post(route('super-admin.tenants.store'), [
                'company_name' => 'New Company',
                'subdomain' => 'new-company',
                'domain' => null,
                'subscription_plan' => 'professional',
                'max_employees' => 150,
                'max_devices' => 20,
                'is_active' => true,
            ]);

        $response->assertRedirect(route('super-admin.tenants.index'));
        $response->assertSessionHas('success');

        expect(Tenant::where('subdomain', 'new-company')->exists())->toBeTrue();

        $tenant = Tenant::where('subdomain', 'new-company')->first();
        expect($tenant->company_name)->toBe('New Company');
        expect($tenant->subscription_plan)->toBe('professional');
        expect($tenant->database_name)->toContain('tenant_');
    });

    test('store validates required fields', function () {
        $response = $this->actingAs($this->superAdmin, 'super_admin')
            ->post(route('super-admin.tenants.store'), [
                'company_name' => '',
                'subdomain' => '',
                'subscription_plan' => '',
            ]);

        $response->assertSessionHasErrors(['company_name', 'subdomain', 'subscription_plan']);
    });

    test('store validates unique subdomain', function () {
        Tenant::create([
            'id' => 'tenant_001',
            'company_name' => 'Existing Company',
            'subdomain' => 'existing',
            'domain' => null,
            'database_name' => 'tenant_existing',
            'database_host' => '127.0.0.1',
            'subscription_plan' => 'professional',
            'max_employees' => 100,
            'max_devices' => 10,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->superAdmin, 'super_admin')
            ->post(route('super-admin.tenants.store'), [
                'company_name' => 'New Company',
                'subdomain' => 'existing',
                'subscription_plan' => 'professional',
                'max_employees' => 100,
                'max_devices' => 10,
            ]);

        $response->assertSessionHasErrors(['subdomain']);
    });

    test('show displays tenant details', function () {
        $tenant = Tenant::create([
            'id' => 'tenant_001',
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

        $response = $this->actingAs($this->superAdmin, 'super_admin')
            ->get(route('super-admin.tenants.show', $tenant->id));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('super-admin/tenants/Show')
            ->has('tenant')
        );
    });

    test('update modifies existing tenant', function () {
        $tenant = Tenant::create([
            'id' => 'tenant_001',
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

        $response = $this->actingAs($this->superAdmin, 'super_admin')
            ->put(route('super-admin.tenants.update', $tenant->id), [
                'company_name' => 'Updated Company A',
                'subscription_plan' => 'enterprise',
                'max_employees' => 200,
                'max_devices' => 30,
                'is_active' => true,
            ]);

        $response->assertRedirect(route('super-admin.tenants.index'));
        $response->assertSessionHas('success');

        $tenant->refresh();
        expect($tenant->company_name)->toBe('Updated Company A');
        expect($tenant->subscription_plan)->toBe('enterprise');
        expect($tenant->max_employees)->toBe(200);
    });

    test('destroy deactivates tenant', function () {
        $tenant = Tenant::create([
            'id' => 'tenant_001',
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

        $response = $this->actingAs($this->superAdmin, 'super_admin')
            ->delete(route('super-admin.tenants.destroy', $tenant->id));

        $response->assertRedirect(route('super-admin.tenants.index'));
        $response->assertSessionHas('success');

        $tenant->refresh();
        expect($tenant->is_active)->toBeFalse();
    });

    test('provision creates tenant database', function () {
        $tenant = Tenant::create([
            'id' => 'tenant_test_provision',
            'company_name' => 'Test Provision Company',
            'subdomain' => 'test-provision',
            'domain' => null,
            'database_name' => 'tenant_test_provision',
            'database_host' => env('DB_HOST', '127.0.0.1'),
            'subscription_plan' => 'professional',
            'max_employees' => 100,
            'max_devices' => 10,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->superAdmin, 'super_admin')
            ->post(route('super-admin.tenants.provision', $tenant->id));

        $response->assertRedirect(route('super-admin.tenants.show', $tenant->id));
        $response->assertSessionHas('success');
    });

    test('requires super admin authentication', function () {
        $response = $this->get(route('super-admin.tenants.index'));
        $response->assertRedirect(route('super-admin.login'));
    });

    test('inactive super admin cannot access', function () {
        $inactiveSuperAdmin = SuperAdmin::factory()->inactive()->create();

        $response = $this->actingAs($inactiveSuperAdmin, 'super_admin')
            ->get(route('super-admin.tenants.index'));

        $response->assertRedirect(route('super-admin.login'));
        $response->assertSessionHas('error');
    });
});
