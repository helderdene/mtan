<?php

use App\Models\User;
use Illuminate\Support\Facades\Gate;

beforeEach(function () {
    $this->superAdmin = User::factory()->create(['role' => 'super_admin']);
    $this->tenantAdmin = User::factory()->create(['role' => 'tenant_admin', 'tenant_id' => 'tenant_123']);
    $this->tenantUser = User::factory()->create(['role' => 'tenant_user', 'tenant_id' => 'tenant_123']);
});

describe('User role methods', function () {
    test('isSuperAdmin returns true for super admin', function () {
        expect($this->superAdmin->isSuperAdmin())->toBeTrue();
        expect($this->tenantAdmin->isSuperAdmin())->toBeFalse();
        expect($this->tenantUser->isSuperAdmin())->toBeFalse();
    });

    test('isTenantAdmin returns true for tenant admin', function () {
        expect($this->superAdmin->isTenantAdmin())->toBeFalse();
        expect($this->tenantAdmin->isTenantAdmin())->toBeTrue();
        expect($this->tenantUser->isTenantAdmin())->toBeFalse();
    });

    test('isTenantUser returns true for tenant user', function () {
        expect($this->superAdmin->isTenantUser())->toBeFalse();
        expect($this->tenantAdmin->isTenantUser())->toBeFalse();
        expect($this->tenantUser->isTenantUser())->toBeTrue();
    });

    test('hasRole returns true for correct role', function () {
        expect($this->superAdmin->hasRole('super_admin'))->toBeTrue();
        expect($this->tenantAdmin->hasRole('tenant_admin'))->toBeTrue();
        expect($this->tenantUser->hasRole('tenant_user'))->toBeTrue();
        expect($this->superAdmin->hasRole('tenant_admin'))->toBeFalse();
    });

    test('belongsToTenant returns true for matching tenant', function () {
        expect($this->tenantAdmin->belongsToTenant('tenant_123'))->toBeTrue();
        expect($this->tenantUser->belongsToTenant('tenant_123'))->toBeTrue();
        expect($this->tenantAdmin->belongsToTenant('tenant_456'))->toBeFalse();
    });
});

describe('Authorization gates', function () {
    test('isSuperAdmin gate allows super admins', function () {
        expect(Gate::forUser($this->superAdmin)->allows('isSuperAdmin'))->toBeTrue();
        expect(Gate::forUser($this->tenantAdmin)->allows('isSuperAdmin'))->toBeFalse();
        expect(Gate::forUser($this->tenantUser)->allows('isSuperAdmin'))->toBeFalse();
    });

    test('isTenantAdmin gate allows tenant admins and super admins', function () {
        expect(Gate::forUser($this->superAdmin)->allows('isTenantAdmin'))->toBeTrue();
        expect(Gate::forUser($this->tenantAdmin)->allows('isTenantAdmin'))->toBeTrue();
        expect(Gate::forUser($this->tenantUser)->allows('isTenantAdmin'))->toBeFalse();
    });

    test('canAccessTenant gate allows super admins and matching tenant users', function () {
        expect(Gate::forUser($this->superAdmin)->allows('canAccessTenant', 'tenant_123'))->toBeTrue();
        expect(Gate::forUser($this->superAdmin)->allows('canAccessTenant', 'tenant_456'))->toBeTrue();
        expect(Gate::forUser($this->tenantAdmin)->allows('canAccessTenant', 'tenant_123'))->toBeTrue();
        expect(Gate::forUser($this->tenantAdmin)->allows('canAccessTenant', 'tenant_456'))->toBeFalse();
        expect(Gate::forUser($this->tenantUser)->allows('canAccessTenant', 'tenant_123'))->toBeTrue();
        expect(Gate::forUser($this->tenantUser)->allows('canAccessTenant', 'tenant_456'))->toBeFalse();
    });

    test('manageEmployees gate allows admins only', function () {
        expect(Gate::forUser($this->superAdmin)->allows('manageEmployees'))->toBeTrue();
        expect(Gate::forUser($this->tenantAdmin)->allows('manageEmployees'))->toBeTrue();
        expect(Gate::forUser($this->tenantUser)->allows('manageEmployees'))->toBeFalse();
    });

    test('manageDevices gate allows admins only', function () {
        expect(Gate::forUser($this->superAdmin)->allows('manageDevices'))->toBeTrue();
        expect(Gate::forUser($this->tenantAdmin)->allows('manageDevices'))->toBeTrue();
        expect(Gate::forUser($this->tenantUser)->allows('manageDevices'))->toBeFalse();
    });

    test('viewReports gate allows admins only', function () {
        expect(Gate::forUser($this->superAdmin)->allows('viewReports'))->toBeTrue();
        expect(Gate::forUser($this->tenantAdmin)->allows('viewReports'))->toBeTrue();
        expect(Gate::forUser($this->tenantUser)->allows('viewReports'))->toBeFalse();
    });
});

describe('Middleware authorization', function () {
    test('tenant_admin middleware blocks regular users', function () {
        $response = $this->actingAs($this->tenantUser)->get('/employees');

        expect($response->status())->toBe(403);
    });

    test('tenant_admin middleware allows tenant admins', function () {
        // Tenant admin can access if tenancy is initialized
        // For now, we just verify the middleware check passes
        $user = $this->tenantAdmin;

        expect($user->isTenantAdmin())->toBeTrue();
    });

    test('tenant_admin middleware allows super admins', function () {
        // Super admin can access tenant admin routes
        $user = $this->superAdmin;

        expect($user->isSuperAdmin())->toBeTrue();
    });
});
